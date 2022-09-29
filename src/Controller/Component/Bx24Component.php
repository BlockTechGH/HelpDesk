<?php
namespace App\Controller\Component;

use App\Model\Entity\Ticket;
use Bitrix24\Exceptions\Bitrix24TokenIsExpiredException;
use Monolog\Logger;
use Cake\Routing\Router;
use Cake\Core\Configure;
use Cake\Controller\Component;
use Monolog\Handler\StreamHandler;
use Bitrix24\Exceptions\Bitrix24ApiException;
use Bitrix24\Exceptions\Bitrix24SecurityException;
use Cake\Http\Client;

class Bx24Component extends Component
{
    public const INCOMMING = 1;
    public const OUTCOMMING = 2;
    public const HTML = 3;
    public const COMPLETED = 'Y';
    public const NOT_COMPLETED = 'N';
    public const PROVIDER_OPEN_LINES = 'IMOPENLINES_SESSION';
    public const PROVIDER_CRM_EMAIL = 'CRM_EMAIL';
    public const PROVIDER_EMAIL = 'EMAIL';
    public const PROVIDER_VOX_CALL = 'VOXIMPLANT_CALL';
    public const PROVIDER_CALL = 'CALL';
    public const PROVIDER_SMS = 'CRM_SMS';
    public const CRM_NEW_ACTIVITY_EVENT = 'ONCRMACTIVITYADD';
    public const CRM_DELETE_ACTIVITY_EVENT = 'ONCRMACTIVITYDELETE';
    public const TICKET_PREFIX = 'GS-';


    private $controller = null;
    private $BitrixTokens = null;
    private $obBx24App = null;
    public $bx24Logger = null;

    private $appBaseUrl = null;

    public function initialize(array $config = []): void
    {
        parent::initialize($config);
        $this->controller = $this->_registry->getController();
        $this->BitrixTokens = $this->controller
            ->getTableLocator()
            ->get('BitrixTokens');

        $logFile = Configure::read('AppConfig.LogsFilePath') . DS . 'bx24app.log';
        $this->bx24Logger = new Logger('BX24');
        $this->bx24Logger->pushHandler(new StreamHandler($logFile, Logger::DEBUG));

        $this->obBx24App = new \Bitrix24\Bitrix24(false, $this->bx24Logger);
        $this->obBx24App->setOnExpiredToken(function() {
            $this->bx24Logger->debug("Access token is expired. Refresh tokens");
            $this->refreshTokens();
            return true;
        });

        $this->obBx24App->setApplicationScope(["crm"]);
        $this->obBx24App->setDomain($this->controller->domain);
        $this->obBx24App->setMemberId($this->controller->memberId);
        $this->obBx24App->setAccessToken($this->controller->authId);
        $this->obBx24App->setRefreshToken($this->controller->refreshId);

        $this->obBx24App->setApplicationId(Configure::read('AppConfig.client_id'));
        $this->obBx24App->setApplicationSecret(Configure::read('AppConfig.client_secret'));
        $appBaseURL = Configure::read('AppConfig.appBaseUrl');
        $this->obBx24App->setRedirectUri($appBaseURL);

        if($this->obBx24App->isAccessTokenExpire())
        {
            $this->bx24Logger->debug("Access token is expired. Refresh tokens");
            $this->refreshTokens();
        }
    }

    #
    #section Task 3. Script for installation application
    #

    public function getInstalledData(): array
    {
        $arData = [
            'activityTypeList' => [],
            'eventList' => []
        ];

        $this->obBx24App->addBatchCall('crm.activity.type.list', [], function($result) use (&$arData)
        {
            if($result['result'])
            {
                $arData['activityTypeList'] = $result['result'];
            }
        });

        $this->obBx24App->addBatchCall('event.get', [], function($result) use (&$arData)
        {
            if($result['result'])
            {
                $arData['eventList'] = $result['result'];
            }
        });

        $this->obBx24App->processBatchCalls();

        $this->bx24Logger->debug('getInstalledData - data', [
            '$arData' => $arData,
        ]);

        return $arData;
    }

    public function installApplicationData($arInstalledData)
    {
        $wwwRoot = Configure::read('App.wwwRoot');
        // "https://icons8.com/icon/6837/служба-поддержки"
        $iconFile = "helpdesk.png";
        $iconPath = $wwwRoot . "img/" . $iconFile;
        $activityDef = static::getActivityTypeAndName();


        // add activity type
        if (!count($arInstalledData['activityTypeList'])) {
            $arActivityTypeParams['fields'] = array_merge(
                [
                    'ICON_FILE' => [
                        $iconFile,
                        base64_encode(file_get_contents($iconPath))
                    ],
                ],
                $activityDef
            );

            $this->obBx24App->addBatchCall('crm.activity.type.add', $arActivityTypeParams, function ($result) use ($arActivityTypeParams) {
                $this->bx24Logger->debug('installApplicationData - crm.activity.type.add', [
                    'arParams' => $arActivityTypeParams,
                    'result' => $result
                ]);
            });
        }

        // Bind/unbind on OnCrmActivityAdd, OnCrmActivityDelete, OnCrmActivityDelete
        $arNeedEvents = [
            'ONCRMACTIVITYADD' => 'crm_activity_handler',
            'ONCRMACTIVITYUPDATE' => 'crm_activity_handler',
            'ONCRMACTIVITYDELETE' => 'crm_activity_handler',
        ];

        foreach($arInstalledData['eventList'] as $event)
        {
            if(isset($arNeedEvents[$event['event']]))
            {
                $arUnbindEventParams = [
                    'event' => $event['event'],
                    'handler' => $event['handler']
                ];

                $this->obBx24App->addBatchCall('event.unbind', $arUnbindEventParams, function($result) use ($arUnbindEventParams)
                {
                    $this->bx24Logger->debug('installApplicationData - event.unbind', [
                        'arParams' => $arUnbindEventParams,
                        'result' => $result
                    ]);
                });
            }
        }

        foreach($arNeedEvents as $event => $routeName)
        {
            $arBindEventParams = [
                'event' => $event,
                'handler' => $this->getRouteUrl($routeName),
            ];
            $this->obBx24App->addBatchCall('event.bind', $arBindEventParams, function($result) use ($arBindEventParams)
            {
                $this->bx24Logger->debug('installApplicationData - event.bind', [
                    'arParams' => $arBindEventParams,
                    'result' => $result
                ]);
            });
        }

        $this->obBx24App->processBatchCalls();
    }

    public function removeActivityTypes($arInstalledData)
    {
        foreach($arInstalledData['activityTypeList'] as $activityType)
        {
            $arParams = [
                'TYPE_ID' => $activityType['TYPE_ID']
            ];

            $this->obBx24App->addBatchCall('crm.activity.type.delete', $arParams, function($result)
            {
            });
        }

        $this->obBx24App->processBatchCalls();
    }

    #
    #endsection
    #

    #
    #section Task 5. Handling incoming event - adding an activity
    #

    public function getActivity($id)
    {
        $arParameters = [
            'filter' => [
                'ID' => $id,
            ],
            'select' => [
                'ASSOCIATED_ENTITY_ID',
                'COMMUNICATIONS',
                'ID',
                'NAME',
                'TYPE_ID',
                'OWNER_ID',
                'OWNER_TYPE_ID',
                'PROVIDER_ID',
                'PROVIDER_TYPE_ID',
                'DIRECTION',
                'DESCRIPTION',
                'RESPONSIBLE_ID',
                "SETTINGS",
                'SUBJECT',
                'ORIGIN_ID'
            ]
        ];
        $response = $this->obBx24App->call('crm.activity.list', $arParameters);
        $this->bx24Logger->debug(__FUNCTION__ . ' - crm.activity.list', [
            'arParameters' => $arParameters,
            'response' => $response['result']
        ]);
        return $response['result'][0];
    }

    public function getTicketSubject(int $ticketId)
    {
        return static::TICKET_PREFIX . $ticketId;
    }

    public function createTicketBy(array $activity, string $subject)
    {
        $ticketActivityTypeIDs = $this->getActivityTypeAndName();
        return $this->createActivity($subject, $ticketActivityTypeIDs, $activity);
    }

    public function createActivity(string $subject, $activityType, array $ownerActivity)
    {
        $parameters = [
            'fields' => [
                'ASSOCIATED_ENTITY_ID' => $ownerActivity["ASSOCIATED_ENTITY_ID"],
                'COMMUNICATIONS' => $ownerActivity['COMMUNICATIONS'],
                'COMPLETED' => static::NOT_COMPLETED,
                'DESCRIPTION' => __(''),
                'DIRECTION' => static::INCOMMING,
                'OWNER_ID' => $ownerActivity['OWNER_ID'],
                'OWNER_TYPE_ID' => $ownerActivity['OWNER_TYPE_ID'],
                'SUBJECT' => $subject,
                'PROVIDER_ID' => 'REST_APP',
                'PROVIDER_TYPE_ID' => $activityType['TYPE_ID'],
                'RESPONSIBLE_ID' => $ownerActivity['RESPONSIBLE_ID']
            ]
        ];
        $response = $this->obBx24App->call('crm.activity.add', $parameters);
        $this->bx24Logger->debug(__FUNCTION__ . ' - crm.activity.add', [
            'arParameters' => $parameters,
            'response' => $response['result']
        ]);
        return $response['result'];
    }

    public function checkOptionalActivity(string $activityProviderId, int $direction)
    {
        return ($activityProviderId == static::PROVIDER_OPEN_LINES
                || $activityProviderId == static::PROVIDER_CRM_EMAIL
                || $activityProviderId == static::PROVIDER_VOX_CALL
                || $activityProviderId == static::PROVIDER_CALL)
            && $activityProviderId != 'REST_APP'
            && $direction == static::INCOMMING;
    }

    public function checkEmailActivity(string $event, string $subject, string $providerTypeName)
    {
        $isEmail = $event == static::CRM_NEW_ACTIVITY_EVENT
            && $providerTypeName == static::PROVIDER_EMAIL;
        $ticketBy = false;
        if ($isEmail) {
            mb_ereg(static::TICKET_PREFIX . '(\d+)', $subject, $matches);
            $ticketBy = count($matches) > 0;
        }
        return $isEmail && !$ticketBy;
    }

    public function checkCallActivity(string $event, string $activityTypeName)
    {
        return $event != static::CRM_DELETE_ACTIVITY_EVENT
            && $activityTypeName == static::PROVIDER_VOX_CALL;
    }

    public function checkOCActivity(string $event, string $providerTypeName)
    {
        return $event == static::CRM_NEW_ACTIVITY_EVENT
            && $providerTypeName == static::PROVIDER_OPEN_LINES;
    }

    #
    #endsection
    #

    #
    #section 6. Sending a response from a ticket
    #

    public function sendMessage($from, $messageText, $ticket, $attachment)
    {
        $source = $this->getActivity($ticket->source_id);
        $currentUser = $this->getCurrentUser();
        $contacts = $this->getContactsFor($currentUser['ID']);
        $currentUser['contacts'] = $contacts;
        $subject = $this->getTicketSubject($ticket->id);
        switch($ticket->source_type_id)
        {
            case static::PROVIDER_CRM_EMAIL:
                return $this->sendEmail($source, $currentUser, $messageText, $subject, $attachment);
            case static::PROVIDER_VOX_CALL:
                return $this->sendSMS($source, $currentUser, $subject, $messageText);
            case static::PROVIDER_OPEN_LINES:
                return $this->sendOCMessage($currentUser, $messageText, $subject, $attachment);
        }
        return null;
    }

    public function getCurrentUser()
    {
        $response = $this->obBx24App->call('user.current', []);
        $this->bx24Logger->debug(__FUNCTION__ . ' - user.current', [
            'result' => $response['result']
        ]);
        return $response['result'];
    }

    public function getContactsFor($clientId)
    {
        $arParameters = [
            'filter' => [
                'ASSIGNED_BY_ID' => $clientId
            ],
            'order' => ['CREATED' => 'ASC']
        ];
        $response = $this->obBx24App->call('crm.contact.list', $arParameters);
        $this->bx24Logger->debug(__FUNCTION__ . ' - crm.contact.list', [
            
        ]);
        return $response['result'];
    }

    public function getMessages(Ticket $ticket) : array
    {
        switch ($ticket->source_type_id) {
            case self::PROVIDER_CRM_EMAIL:
                return $this->getEmailsBy($ticket);
                break;

            case self::PROVIDER_OPEN_LINES:
                return $this->getOCMessages($ticket->source_id, $ticket->id);

            case self::PROVIDER_SMS:
                return [];

            default:
                return [];
        }
    }

    public function getOCMessages(int $chatId, int $ticketId) : array
    {
        $arParameters = [
            'DIALOG_ID' => "chat{$chatId}",
            'LIMIT' => 20,
        ];
        $response = $this->obBx24App->send('im.dialog.messages.get', $arParameters);
        $this->bx24Logger->debug(__FUNCTION__ . ' - im.dialog.messages.get', [
            'arParameters' => $arParameters,
            'result' => $response['result']
        ]);

        $subject = $this->getTicketSubject($ticketId);
        $messages = [];
        foreach ($response['result']['messages'] as $arMessage)
        {
            $user = $this->findUserIn($arMessage['author_id'], $response['result']['users']);
            if($user == null)
            {
                continue;
            }
            $messages[] = $this->makeMessageStructure($user['name'], $arMessage['text'], $subject, null);
        }
        return $messages;
    }

    public function getEmailsBy(Ticket $ticket) : array
    {
        $email = $this->getActivity($ticket->source_id);
        $subject = "%{$this->getTicketSubject($ticket->id)}%";
        $messages = [
            $this->makeMessageStructure($email['SETTINGS']['EMAIL_META']['from'], $email['DESCRIPTION'], $subject, null),
        ];

        $arParameters = [
            'filter' => [
                'SUBJECT' => $subject,
                'TYPE_ID' => 'E-mail'
            ],
            'order' => [
                'CREATED' => 'ASK'
            ],
            'select' => [
                'SETTINGS',
                'DESCRIPTION'
            ]
        ];
        $response = $this->obBx24App->call('crm.activity.list', $arParameters);
        $this->bx24Logger->debug(__FUNCTION__ . ' - crm.activity.list', [
            'arParameters' => $arParameters,
            'response' => $response,
        ]);

        foreach($response['result'] as $arActivity)
        {
            $messages[] = $this->makeMessageStructure($arActivity['SETTINGS']['EMAIL_META']['from'], $arActivity['DESCRIPTION'], $subject, null);
        }

        return  $messages;
    }

    private function findUserIn($userId, array $enum)
    {
        foreach($enum as $user)
        {
            if($user['id'] == $userId)
            {
                return $user;
            }
        }
        return null;
    }

    private function sendEmail($startEmail, $currentUser, string $text, string $subject, $attachment)
    {
        $from = $currentUser['WORK_EMAIL'] ?? $currentUser['EMAIL'];
        $arParameters = array_merge_recursive([], $startEmail);
        $arParameters['COMMUNICATIONS'] = [[
            'ENTITY_ID' => $currentUser['ID'],
            'ENTITY_TYPE_ID' => 3,
            'VALUE' => $startEmail['SETTINGS']['EMAIL_META']['from'],
        ]];
        $arParameters['SUBJECT'] = $subject;
        $arParameters['DIRECTION'] = static::INCOMMING;
        $arParameters['START_TIME'] = date(DATE_ATOM);
        $arParameters['END_TIME'] = $arParameters['START_TIME'];
        $arParameters['RESPONSIBLE_ID'] = $currentUser['ID'];
        $arParameters['COMPLETED'] = static::COMPLETED;
        $arParameters['DESCRIPTION'] = $text;
        $arParameters['DESCRIPTION_TYPE'] = static::HTML;
        $arParameters['SETTINGS'] = [
            'MESSAGE_FROM' => implode(
                ' ',
                [$currentUser['NAME'], $currentUser['LAST_NAME'], '<' . $from . '>']
            ),
        ];
        $response = $this->obBx24App->call('crm.activity.add', $arParameters);
        $this->bx24Logger->debug(__FUNCTION__ . ' - crm.activity.add', [
            'arParameters' => $arParameters,
            'result' => $response['result']
        ]);

        return $this->makeMessageStructure($from, $text, $subject, $attachment);
    }

    private function sendSMS($arCall, $currentUser, string $subject, string $text)
    {
        $from = $currentUser['WORK_PHONE'] ?? $currentUser['PHONE'];
        $arParameters = [
            'ASSOCIATED_ENTITY_ID' => $arCall['ASSOCIATED_ENTITY_ID'],
            'SUBJECT' => $subject,
            'DIRECTION' => static::INCOMMING,
            'DESCRIPTION' => $text,
            'START_TIME' => date(DATE_RFC822),
            'END_TIME' => date(DATE_RFC822),
            'COMPLETED' => static::COMPLETED,
            'TYPE_ID' => 6,
            "PROVIDER_ID" => "CRM_SMS",
            "PROVIDER_TYPE_ID" => "SMS",
            'RESPONSIBLE_ID' => $currentUser['ID'],
            'COMMUNICATIONS' => [
                [
                    "TYPE" => "PHONE",
                    "VALUE" => $from,
                    "ENTITY_ID" => $currentUser['ID'],
                    "ENTITY_TYPE_ID" => "1"
                ]
            ]
        ];
        $response = $this->obBx24App->call('crm.activity.add', $arParameters);
        $this->bx24Logger->debug(__FUNCTION__ . '- crm.activity.add', [
            'arParameters' => $arParameters,
            'response' => $response,
        ]);

        return $this->makeMessageStructure($from, $text, $subject, null);
    }

    private function sendOCMessage($currentUser, $ticket, string $text, $attachment)
    {
        $arParameters = [
            'DIALOG_ID' => 'chat'.$ticket->source_id,
            'MESSAGE' => $text,
            'ATTACH' => $attachment
        ];
        $response = $this->obBx24App->call('im.message.add', $arParameters);
        $this->bx24Logger->debug('handleCrmActivity - sendMessage - sendOCMessage - im.message.add', [
            'arParameters' => $arParameters,
            'response' => $response
        ]);

        return $this->makeMessageStructure($currentUser['TITLE'], $text, "#-{$ticket->id}", $attachment);
    }

    private function makeMessageStructure(string $from, string $text, string $theme, $attachment) : array
    {
        return [
            'from' => $from,
            'date' => date(DATE_ATOM),
            'text' => $text,
            'theme' => $theme,
            'attachment' => $attachment
        ];
    }

    #
    #endsection
    #

    private static function getActivityTypeAndName()
    {
        $postfix = Configure::read('AppConfig.itemsPostfix');
        return [
            'TYPE_ID' => 'HELPDESK_TICKETING' . (($postfix) ? "_" . strtoupper($postfix) : ''),
            'NAME' => __('Helpdesk Ticketing') . (($postfix) ? " " . $postfix : ''),
        ];
    }

    private function getRouteUrl(string $routeName)
    {
        if (!$this->appBaseUrl) {
            $this->appBaseUrl = Configure::read('AppConfig.appBaseUrl');
        }
        $routeProps = ['_name' => $routeName ];
        return ($this->appBaseUrl)
            ? $this->appBaseUrl . Router::url($routeProps, false)
            : Router::url($routeProps, true);
    }

    private function refreshTokens()
    {
        $oldAccessToken = $this->obBx24App->getAccessToken();
        $oldRefreshToken = $this->obBx24App->getRefreshToken();
        $tokensRefreshResult = $this->obBx24App->getNewAccessToken();
        $this->bx24Logger->debug('refreshTokens - getNewAccessToken - result', [
            'tokensRefreshResult' => $tokensRefreshResult
        ]);
        $this->obBx24App->setAccessToken($tokensRefreshResult["access_token"]);
        $this->obBx24App->setRefreshToken($tokensRefreshResult["refresh_token"]);

        $this->BitrixTokens->writeAppTokens(
            $this->obBx24App->getMemberId(),
            $this->obBx24App->getDomain(),
            $this->obBx24App->getAccessToken(),
            $this->obBx24App->getRefreshToken(),
            $tokensRefreshResult["expires_in"]
        );

        $this->bx24Logger->debug("refreshTokens - status", [
            "Access token refreshed" => $oldAccessToken != $tokensRefreshResult["access_token"],
            "Refresh token refreshed" => $oldRefreshToken != $tokensRefreshResult["refresh_token"],
        ]);
    }
}
