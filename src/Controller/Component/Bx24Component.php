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
use Cake\I18n\FrozenTime;
use Exception;

class Bx24Component extends Component
{
    public const INCOMMING = 1;
    public const OUTCOMMING = 2;
    public const STATUS_AWAIT = 1;
    public const STATUS_COMPLETED = 2;
    public const HTML = 3;
    public const PLAIN_TEXT = 1;
    public const USER_TYPE = 6;
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
    public const DATE_TIME_FORMAT = "m/d/Y h:i a";

    public const OWNER_TYPE_CONTACT = 3;
    public const ACTIVITY_TYPE_EMAIL = 4;
    public const CRM_ENTITY_TYPES_IDS = [
        'CRM_DEAL' => 2,
        'CRM_CONTACT' => 3,
        'CRM_COMPANY' => 4
    ];

    public const BITRIX_REST_API_RESULT_LIMIT_COUNT = 50;

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

        $this->obBx24App->addBatchCall('placement.get', [], function($result) use (&$arData)
        {
            if($result['result'])
            {
                $arData['placementList'] = $result['result'];
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

        // add placements in crm card
        $arNeedPlacements = [
            'CRM_CONTACT_DETAIL_ACTIVITY',
            'CRM_COMPANY_DETAIL_ACTIVITY',
            'CRM_DEAL_DETAIL_ACTIVITY'
        ];

        $placementList = isset($arInstalledData['placementList']) ? $arInstalledData['placementList'] : [];
        foreach($placementList ?? [] as $placement)
        {
            // unbind old placements firts
            if(in_array($placement['placement'], $arNeedPlacements))
            {
                $arUnbindParams = [
                    'PLACEMENT' => $placement['placement'],
                    'HANDLER' => $placement['handler']
                ];

                $this->obBx24App->addBatchCall('placement.unbind', $arUnbindParams, function($result) use ($arUnbindParams)
                {
                    $this->bx24Logger->debug('installApplicationData - placement.unbind', [
                        'arParams' => $arUnbindParams,
                        'result' => $result
                    ]);
                });
            }
        }

        $handler = $this->getRouteUrl('crm_interface');
        foreach($arNeedPlacements as $placement)
        {
            $arBindParams = [
                'PLACEMENT' => $placement,
                'HANDLER' => $handler,
                'LANG_ALL' => [
                    'en' => [
                        'TITLE' => __('Helpdesk')
                    ]
                ]
            ];

            $this->obBx24App->addBatchCall('placement.bind', $arBindParams, function($result) use ($arBindParams)
            {
                $this->bx24Logger->debug('installApplicationData - placement.bind', [
                    'arParams' => $arBindParams,
                    'result' => $result
                ]);
            });
        }

        // Bind/unbind on OnCrmActivityAdd, OnCrmActivityDelete
        $arNeedEvents = [
            'ONCRMACTIVITYADD' => 'crm_activity_handler',
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

    public function getActivities($ids)
    {
        $arParameters = [
            'filter' => [
                'ID' => [],
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
                'ORIGIN_ID',
                'PROVIDER_PARAMS',
                'CREATED',
                'COMPLETED'
            ]
        ];
        if(!is_array($ids))
        {
            $ids = [$ids];
        }
        $activities = [];
        $list = [];

        // Accumulate activities in variable
        $chunks = array_chunk($ids, static::BITRIX_REST_API_RESULT_LIMIT_COUNT);
        foreach($chunks as $i => $idsChunk)
        {
            $this->bx24Logger->debug(__FUNCTION__ . ' - crm.activity.list - chunk #' . strval($i + 1) . ' in process');
            $arParameters['filter']['ID'] = $idsChunk;
            $response = $this->obBx24App->call('crm.activity.list', $arParameters);
            if(isset($response['error']))
            {
                $this->bx24Logger->error(__FUNCTION__ . ' - crm.activity.list - error', [
                    'chunk' => $i+1,
                    'filter' => $arParameters['filter'],
                    'context' => $response
                ]);
            }
            if(count($response['result']) == 0)
            {
                continue;
            }
            $list = array_merge($list, $response['result']);
        }


        foreach($list as $activity)
        {
            $activities[$activity['ID']] = $activity;
        }
        return count($activities) > 0 ? $activities : null;
    }

    public function getActivityById($activityId)
    {
        $response = $this->obBx24App->call('crm.activity.get', ['id' => $activityId]);

        $this->bx24Logger->debug(__FUNCTION__ . ' - activity data', [
            'result' => $response
        ]);
        return $response['result'];
    }

    public function getActivityAndRelatedDataById($activityId)
    {
        $arResult = [
            'activity' => [],
            'bindings' => []
        ];

        $arActivityParams = [
            'filter' => [
                'ID' => $activityId
            ],
            'select' => ['*', 'COMMUNICATIONS']
        ];

        $this->obBx24App->addBatchCall('crm.activity.list', $arActivityParams, function($result) use (&$arResult)
        {
            $this->bx24Logger->debug(__FUNCTION__ . ' - get activity data', [
                'result' => $result
            ]);

            if($result['result'])
            {
                $arResult['activity'] = $result['result'][0];
            }
        });

        $this->obBx24App->addBatchCall('crm.activity.binding.list', ['activityId' => $activityId], function($result) use (&$arResult)
        {
            $this->bx24Logger->debug(__FUNCTION__ . ' - get bindings', [
                'result' => $result
            ]);

            if($result['result'])
            {
                $arResult['bindings'] = $result['result'];
            }
        });

        $this->obBx24App->processBatchCalls();
        return $arResult;
    }

    public function getTicketSubject(int $ticketId)
    {
        return static::TICKET_PREFIX . $ticketId;
    }

    public function createActivity(array $ownerActivity, string $subject)
    {
        $now = FrozenTime::now();

        $parameters = [
            'fields' => [
                'ASSOCIATED_ENTITY_ID' => $ownerActivity["ASSOCIATED_ENTITY_ID"],
                'COMMUNICATIONS' => $ownerActivity['COMMUNICATIONS'],
                'COMPLETED' => static::NOT_COMPLETED,
                'DESCRIPTION' => $ownerActivity['DESCRIPTION'] ?? '',
                'DESCRIPTION_TYPE' => $ownerActivity['DESCRIPTION_TYPE'] ?? '',
                'DIRECTION' => static::INCOMMING,
                'OWNER_ID' => $ownerActivity['OWNER_ID'],
                'OWNER_TYPE_ID' => $ownerActivity['OWNER_TYPE_ID'],
                'SUBJECT' => $subject . ' ' . $ownerActivity['SUBJECT'],
                'PROVIDER_ID' => 'REST_APP',
                'PROVIDER_TYPE_ID' => $ownerActivity['PROVIDER_TYPE_ID'],
                'RESPONSIBLE_ID' => $ownerActivity['RESPONSIBLE_ID'],
                'START_TIME' => $now->i18nFormat('yyyy-MM-dd HH:mm:ss'),
            ]
        ];
        $this->bx24Logger->debug('createActivity', $parameters);
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
        return $event == static::CRM_NEW_ACTIVITY_EVENT
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

    public function sendMessage($from, string $messageText, Ticket $ticket, $attachment, $currentUser, $contactEmail = '')
    {
        $this->bx24Logger->debug(__FUNCTION__ . ' - input params', [
            'messageText' => $messageText,
            'ticket' => $ticket,
            'currentUser' => $currentUser
        ]);

        $source = [];
        if($ticket->source_id)
        {
            $source = current($this->getActivities([$ticket->source_id]));
        }

        if (!$source) {
            $source = current($this->getActivities([$ticket->action_id]));
        }
        if (!$source) {
            throw new Exception("Activity-{$ticket->source_type_id} is not found");
        }
        $this->bx24Logger->debug(__FUNCTION__ . ' - getActivity', [
            'source_id' => $ticket->source_id,
            'source_type_id' => $ticket->source_type_id,
            'source' => $source,
        ]);
        $currentUser['contacts'] = $this->getContactsFor($currentUser['ID']);
        $subject = $this->getTicketSubject($ticket->id);
        $arParameters = static::prepareNewActivityParameters($source, $currentUser, $subject, $messageText);
        $appProps = $this->getActivityTypeAndName();

        $this->filterEmailsForCommunications($arParameters);
        $this->addCommunicationsFromEmail($arParameters, $contactEmail);

        $this->bx24Logger->debug(__FUNCTION__ . ' - parameters', [
            'subject' => $subject,
            'user contacts' => $currentUser['contacts'],
            'arParameters' => $arParameters,
        ]);

        switch($ticket->source_type_id)
        {
            // email
            case static::PROVIDER_CRM_EMAIL:
                return $this->sendEmail($arParameters, $currentUser, $attachment);
            // call
            case static::PROVIDER_VOX_CALL:
                return $this->sendEmail($arParameters, $currentUser, $attachment);
            // not used case - response from BX24 widjet
            case static::PROVIDER_OPEN_LINES:
                return $this->sendOCMessage($source, $currentUser, $messageText, $subject, $attachment);
            // manually created
            case $appProps['TYPE_ID']:
                return $this->sendEmail($arParameters, $currentUser, $attachment);
        }
        return null;
    }

    private function addCommunicationsFromEmail(&$arParameters, $contactEmail)
    {
        if($contactEmail && !$arParameters['COMMUNICATIONS'])
        {
            $arParameters['COMMUNICATIONS'] = [[
                'VALUE' => $contactEmail
            ]];
        }
    }

    private function filterEmailsForCommunications(&$arParameters)
    {
        $arCommunications = $arParameters['COMMUNICATIONS'];
        $arFilteredCommunications = [];
        foreach($arCommunications as $communication)
        {
            if(filter_var($communication['VALUE'], FILTER_VALIDATE_EMAIL))
            {
                $arFilteredCommunications[] = $communication;
            }
        }

        $arParameters['COMMUNICATIONS'] = $arFilteredCommunications;
        return $arParameters;
    }

    public function getCurrentUser()
    {
        $response = $this->obBx24App->call('user.current', []);
        $this->bx24Logger->debug(__FUNCTION__ . ' - user.current', [
            'result' => $response['result']
        ]);
        return $response['result'];
    }

    public function getUserById(array $uids)
    {
        $result = [];
        $chunks = array_chunk($uids, static::BITRIX_REST_API_RESULT_LIMIT_COUNT);
        foreach($chunks as $userIds)
        {
            $arParameters = ['FILTER' => [ 'ID' => $uids ], "ADMIN_MODE" => 'True'];
            $fetched = $this->obBx24App->call('user.get', $arParameters)['result'];
            $result = array_merge($result, $fetched);
        }

        return count($result) > 0 ? (count($uids) > 0 ? $result : $result[0]) : null;
    }

    public function getDepartmentsByIds(array $ids)
    {
        $result = [];
        $arParameters = ['ID' => $ids];
        $response = $this->obBx24App->call('department.get', $arParameters)['result'];
        if(!$response)
        {
            return $result;
        }
        return $response;
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
            'arParameters' => $arParameters,
            'response' => $response,
        ]);
        return $response['result'];
    }

    public function getCompanyInfo($id)
    {
        $response = $this->obBx24App->call('crm.company.get', ['id' => $id]);
        $this->bx24Logger->debug(__FUNCTION__ . ' - crm.company.get', [
            'id' => $id,
            'response' => $response,
        ]);
        return $response['result'];
    }

    public function getContactInfo($id)
    {
        $response = $this->obBx24App->call('crm.contact.get', ['id' => $id]);
        $this->bx24Logger->debug(__FUNCTION__ . ' - crm.contact.get', [
            'id' => $id,
            'response' => $response,
        ]);
        return $response['result'];
    }

    public function getLeadInfo($id)
    {
        $response = $this->obBx24App->call('crm.lead.get', ['id' => $id]);
        $this->bx24Logger->debug(__FUNCTION__ . ' - crm.lead.get', [
            'id' => $id,
            'response' => $response,
        ]);
        return $response['result'];
    }

    public function getTicketAttributes($ticketIds)
    {
        $result = [];
        $activities = $this->getActivities($ticketIds);
        $this->bx24Logger->debug(__FUNCTION__, [
            'id' => $ticketIds,
            'activity' => $activities
        ]);

        if(!$activities)
        {
            return null;
        }

        $uids = array_values(array_unique(array_column($activities, 'RESPONSIBLE_ID')));
        $responsibles = $this->getUsersAttributes($uids);

        $unfound =  [
            'id' => 0,
            'abr' => "",
            'title' => "User not found",
            'email' => "",
            'phone' => "",
        ];
        foreach($activities as $id => $activity)
        {
            if (!$activity)
            {
                $result[$id] = null;
                continue;
            }
            $record = $this->getOneTicketAttributes($activity);
            if (!$record) {
                $result[$id] = null;
                continue;
            }
            if (isset($responsibles[$record['responsible']]))
            {
                $record['responsible'] = $responsibles[$record['responsible']];
            } else {
                $record['responsible'] = array_merge(
                    [],
                    $unfound,
                    [
                        'id' => $record['responsible'],
                        'title' => "User with ID #{$record['responsible']} not found in Bitrix24"
                    ]
                );
            }
            $result[$id] = $record;
        }
        return $result;
    }

    public function getOneTicketAttributes($ticketActivity)
    {
        if(!$ticketActivity)
        {
            $this->bx24Logger->error(__FUNCTION__ . ' - ticket/source activity not found');
            return null;
        }
        $customerContacts = $ticketActivity['COMMUNICATIONS'][0];
        $customerNames = $customerContacts['ENTITY_SETTINGS'];
        $additionContact = null;
        if(isset($ticketActivity['COMMUNICATIONS'][1]))
        {
            $additionContact = $ticketActivity['COMMUNICATIONS'][1];
            if(!$customerNames)
            {
                $customerNames = $additionContact['ENTITY_SETTINGS'];
            }
        }

        $result = [
            'id' => intval($ticketActivity['ID']),
            'ENTITY_TYPE_ID' => (int)$customerContacts['ENTITY_TYPE_ID'],
            'responsible' => intval($ticketActivity['RESPONSIBLE_ID']),
            'customer' => [
                'id' => (int)$customerContacts['ENTITY_ID'],
                'abr' => $this->makeNameAbbreviature($customerNames),
                'title' => isset($customerNames) && isset($customerNames['NAME']) ? $this->makeFullName($customerNames) : "",
                'email' => $customerContacts['TYPE'] == 'EMAIL' || strstr($customerContacts['VALUE'], '@')
                    ? $customerContacts['VALUE']
                    : ($additionContact && ($additionContact['TYPE'] == 'EMAIL' || strstr($customerContacts['VALUE'], '@'))
                        ? $additionContact['VALUE']
                        : ''
                    ),
                'phone' => $customerContacts['TYPE'] == 'PHONE'
                    ? $customerContacts['VALUE']
                    : ($additionContact && $additionContact['TYPE'] == 'PHONE'
                        ? $additionContact['VALUE']
                        : ''
                    ),
            ],
            'subject' => $ticketActivity['SUBJECT'],
            'text' => $ticketActivity['DESCRIPTION'],
            'date' => date(static::DATE_TIME_FORMAT, strtotime($ticketActivity['CREATED'])),
            'active' => $ticketActivity['COMPLETED'] == self::NOT_COMPLETED,
            'PROVIDER_PARAMS' => $ticketActivity['PROVIDER_PARAMS'],
            'PRIOVIDER_ID' => $ticketActivity['PROVIDER_ID']
        ];

        if (!$result['customer']['email'] || !$result['customer']['phone'])
        {
            switch ($result['ENTITY_TYPE_ID'])
            {
                // lead
                case 1:
                    $entityInfo = $this->getLeadInfo($customerContacts['ENTITY_ID']);
                    break;
                // contact
                case 3:
                    $entityInfo = $this->getContactInfo($customerContacts['ENTITY_ID']);
                    break;
                // company
                case 4:
                    $entityInfo = $this->getCompanyInfo($customerContacts['ENTITY_ID']);
                    break;
            }
            if (!$result['customer']['email'] && array_key_exists('EMAIL', $entityInfo))
            {
                $result['customer']['email'] = $entityInfo['EMAIL'][0]['VALUE'];
            }
            if (!$result['customer']['phone'] && array_key_exists('PHONE', $entityInfo))
            {
                $result['customer']['phone'] = $entityInfo['PHONE'][0]['VALUE'];
            }
        }

        return $result;
    }

    public function getUsersAttributes(array $uids)
    {
        $result = [];
        $resords = $this->getUserById($uids);
        foreach($resords as $record)
        {
            $uid = (int)$record["ID"];
            $result[$uid] = $this->makeUserAttributes($record ?? []);
        }

        return $result;
    }

    public function makeUserAttributes(array $record) : array
    {
        if(count($record) == 0)
        {
            return [
                'id' => 0,
                'abr' => 'A N Onim',
                'title' => 'Client undefined',
                'email' => '',
                'phone' => '',
                'company' => '',
            ];
        }
        return [
            'id' => (int)$record["ID"],
            'abr' => $this->makeNameAbbreviature($record),
            'title' => $this->makeFullName($record),
            'email' => $record['EMAIL'],
            'phone' => $record['UF_PHONE_INNER'] ?? $record['PERSONAL_PHONE'] ?? $record['PERSONAL_MOBILE'] ?? $record['PHONE'] ?? "",
            'company' => $record['WORK_COMPANY'],
            'photo' => $record['PERSONAL_PHOTO'] ?? ''
        ];
    }

    public function makeCompanyAttributes(array $company) : array
    {
        return [
            'id' => (int)$company["ID"],
            'abr' => mb_substr($company['TITLE'], 0, 1),
            'title' => $company['TITLE'],
            'email' => $company['EMAIL']?? '',
            'phone' => $company['PHONE']?? '',
            'photo' => $company['LOGO'] ?? ''
        ];
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

    public function getFirstMessageInOpenChannelChat(array $source)
    {
        $arResult = [];

        // GET ID CHAT
        $getChatParameters = [
            'ENTITY_ID' => $source['PROVIDER_PARAMS']['USER_CODE'],
            'ENTITY_TYPE' => 'LINES'
        ];

        $getChatParametersCmd = $this->obBx24App->addBatchCall('im.chat.get', $getChatParameters, function($result) use (&$arResult)
        {
            $arResult['dialogId'] = 'chat' . intval($result['result']['ID']);
        });

        // GET FIRST MESSAGE
        $arChatMessageParameters = [
            'DIALOG_ID' => "chat" . '$result[' . $getChatParametersCmd . '][ID]',
            'FIRST_ID' => 0,
            'LIMIT' => 5, // Can be system: Conversation started, Data received, Enquiry assigned to, etc.
        ];

        $this->obBx24App->addBatchCall('im.dialog.messages.get', $arChatMessageParameters, function($result) use (&$arResult)
        {
            $noSystemMessages = array_filter($result['result']['messages'], function ($message)
            {
                return $message['author_id'] != 0;
            });

            $arResult['message'] = array_pop($noSystemMessages);
        });

        $this->obBx24App->processBatchCalls();

        return $arResult;
    }

    public function getOCMessages(int $chatId, int $ticketId) : array
    {
        $subject = "%{$this->getTicketSubject($ticketId)}%";
        $arParameters = [
            'filter' => [
                'SUBJECT' => $subject,
                'PROVIDER_ID' => 'REST_APP',
                'ASSOCIATED_ENTITY_ID' => $chatId
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

        return  [];
    }

    public function getEmailsBy(Ticket $ticket) : array
    {
        $subject = "%{$this->getTicketSubject($ticket->id)}%";
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

        return  [];
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

    private function sendEmail(array &$arParameters, $currentUser, $attachment)
    {
        $from = $currentUser['WORK_EMAIL'] ?? $currentUser['EMAIL'];
        //
        $arParameters['TYPE_ID'] = static::ACTIVITY_TYPE_EMAIL;
        $arParameters['DESCRIPTION'] = str_replace(array("\r\n", "\r", "\n"), '<br>', $arParameters['DESCRIPTION']);
        $arParameters['DESCRIPTION_TYPE'] = self::HTML;
        $arParameters['SETTINGS'] = [
            'MESSAGE_FROM' => implode(
                ' ',
                [$currentUser['NAME'], $currentUser['LAST_NAME'], '<' . $from . '>']
            ),
        ];
        if (!is_array($attachment)) {
            $attachment = [$attachment];
        }
        $arParameters['FILES'] = $this->getFileAttachArray($attachment);

        $this->createActivityWith($arParameters);

        return $this->makeMessageStructure(
            $arParameters['SETTINGS']['MESSAGE_FROM'],
            $arParameters['DESCRIPTION'],
            $arParameters['SUBJECT'],
            $attachment
        );
    }

    private function sendSMS(array &$arParameters, $currentUser, array $attachments)
    {
        $from = $currentUser['WORK_PHONE'] ?? $currentUser['PHONE'];
        $arParameters['TYPE_ID'] = 6;
        $arParameters['PROVIDER_ID'] = static::PROVIDER_SMS;
        $arParameters['PROVIDER_TYPE_ID']=  'SMS';
        $arParameters['FILES'] = $this->getFileAttachArray($attachments);

        $this->createActivityWith($arParameters);

        return $this->makeMessageStructure(
            $from,
            $arParameters['DESCRIPTION'],
            $arParameters['SUBJECT'],
            $attachments
        );
    }

    private function sendOCMessage($source, $currentUser, string $text, string $subject, $attachment)
    {
        // GET ID CHAT
        $arParameters = [
            'ENTITY_ID' => $source['PROVIDER_PARAMS']['USER_CODE'],
            'ENTITY_TYPE' => 'LINES'
        ];
        $chat = $this->obBx24App->call('im.chat.get', $arParameters)['result'];
        if (!is_array($attachment)) {
            $attachment = [$attachment];
        }

        // Check: current user in chat
        $arParameters = [
            'DIALOG_ID' => "chat{$chat['ID']}",
        ];
        $response = $this->obBx24App->call('im.dialog.users.list', $arParameters);
        $interlocutorIDs = array_column($response['result'], 'id');
        $this->bx24Logger->debug(__FUNCTION__ . ' - users', [
            'chat.users' => $interlocutorIDs,
            'responsible' => $source['RESPONSIBLE_ID'],
            'currentUser' => $currentUser['ID']
        ]);

        if(!in_array($currentUser['ID'], $interlocutorIDs))
        {
            $arParameters['CHAT_ID'] = $arParameters['DIALOG_ID'];
            $arParameters['USERS'] = [ $currentUser['ID'] ];
            $this->bx24Logger->debug(__FUNCTION__ . ' - im.chat.user.add', [
                'arParameters' => $arParameters
            ]);
            $response = $this->obBx24App->call('im.chat.user.add', $arParameters);
        }

        $arParameters = [
            'DIALOG_ID' => 'chat'.$chat['ID'],
            'MESSAGE' => $text,
        ];
        $arParameters['ATTACH'] = $this->saveFilesAndMakeAttach($attachment, $currentUser['ID']);
        $response = $this->obBx24App->call('im.message.add', $arParameters);
        $this->bx24Logger->debug('handleCrmActivity - sendMessage - sendOCMessage - im.message.add', [
            'arParameters' => $arParameters,
            'response' => $response,
        ]);

        return $this->makeMessageStructure($currentUser['NAME'], $text, $subject, $attachment);
    }

    private function makeMessageStructure(string $from, string $text, string $theme, $attachment) : array
    {
        return [
            'from' => $from,
            'date' => date(static::DATE_TIME_FORMAT),
            'text' => $text,
            'theme' => $theme,
            'attachment' => $attachment
        ];
    }

    private function makeNameAbbreviature($person)
    {
        $f = mb_substr($person['NAME'] ?? "", 0, 1);
        $s = mb_substr($person['SECOND_NAME'] ?? "", 0, 1);
        $l = mb_substr($person['LAST_NAME'] ?? "", 0, 1);
        return "$f$s$l";
    }

    private function makeFullName(array $arNames)
    {
        return trim(trim($arNames['NAME'] . ' ' . $arNames['SECOND_NAME']) . ' ' . $arNames['LAST_NAME']);
    }

    private function createActivityWith(array $arParameters)
    {
        $this->bx24Logger->debug('createActivityWith - crm.activity.add - fields', $arParameters);
        $response = $this->obBx24App->call('crm.activity.add', ['fields' => $arParameters]);
        $this->bx24Logger->debug(__FUNCTION__ . '- crm.activity.add', [
            'arParameters' => $arParameters,
            'response' => $response
        ]);
        return $response['result'];
    }

    private static function prepareNewActivityParameters(array $source, $currentUser, string $subject, $text)
    {
        if(stripos($source['SUBJECT'], $subject) !== false)
        {
            $activitySubject = $source['SUBJECT'];
        } else {
            $activitySubject = $subject . " " . $source['SUBJECT'];
        }

        return [
            'OWNER_ID' => $source['OWNER_ID'],
            'OWNER_TYPE_ID' => $source['OWNER_TYPE_ID'],
            'ASSOCIATED_ENTITY_ID' => $source['ASSOCIATED_ENTITY_ID'],
            'TYPE_ID' => $source['TYPE_ID'],
            'SUBJECT' => $activitySubject,
            'DESCRIPTION' => $text,
            'RESPONSIBLE_ID' => $currentUser['ID'],
            'START_TIME' => date(static::DATE_TIME_FORMAT),
            'END_TIME' => date(static::DATE_TIME_FORMAT),
            'COMPLETED' => static::COMPLETED,
            'DIRECTION' => static::OUTCOMMING,
            'COMMUNICATIONS' => static::copyContacts($source['COMMUNICATIONS']) ?? array_map(function ($item) use ($source) {
                return [
                    'ENTITY_ID' => $source,
                    'ENTITY_TYPE_ID' => 3,
                    'VALUE' => $item['EMAIL'] ?? $item['NAME']
                ];
            }, $currentUser['contacts']),
        ];
    }

    private static function copyContacts($contacts)
    {
        return array_map(function ($contact) {
            return [
                'ENTITY_ID' => $contact['ENTITY_ID'],
                'ENTITY_TYPE_ID' => $contact['ENTITY_TYPE_ID'],
                'VALUE' => $contact['VALUE']
            ];
        }, $contacts);
    }

    #
    #endsection
    #

    #section 7. Reopen/close button

    public function setCompleteStatus($idActivity, bool $value)
    {
        $arParameters = [
            'id' => $idActivity,
            'fields' => [
                'COMPLETED' => !$value ? static::COMPLETED : static::NOT_COMPLETED,
                'STATUS' => !$value ? static::STATUS_COMPLETED : static::STATUS_AWAIT,
            ]
        ];
        $this->obBx24App->call('crm.activity.update', $arParameters);
        $this->bx24Logger->debug(__FUNCTION__ . "Set complition of activity #{$idActivity} to '{$arParameters['fields']['COMPLETED']}'");
    }

    #endsection

    #section Manual ticket's creation

    public function prepareNewActivitySource(
        $entityId,
        string $entityType,
        string $subject,
        string $description,
        int $responsibleId,
        array $communications
    ) {
        $type = $this->getActivityTypeAndName();
        return [
            'COMMUNICATIONS' => $communications,
            'ASSOCIATED_ENTITY_ID' => $entityId,
            'OWNER_ID' => $entityId,
            'OWNER_TYPE_ID' => self::CRM_ENTITY_TYPES_IDS[$entityType],
            'PROVIDER_ID' => 'REST_APP',
            'PROVIDER_TYPE_ID' => $type['TYPE_ID'],
            'SUBJECT' => $subject,
            'DESCRIPTION' => $description,
            'RESPONSIBLE_ID' => $responsibleId,
            'ID' => null,
        ];
    }

    public function getContact(int $contactID)
    {
        $contact = $this->getBitrixEntity("crm.contact.get", $contactID, __FUNCTION__);
        return $contact;
    }

    public function getCompany(int $companyID)
    {
        $parameters = ['filter' => ['ID' => [$companyID]], 'select' => ['*', 'UF_*', 'CONTACTS']];
        $result = $this->obBx24App->call('crm.company.list', $parameters);
        $this->bx24Logger->debug(__FUNCTION__ . ' - crm.company.list', [
            'arrParameters' => $parameters,
            'arrResult' => $result
        ]);
        $company = $result['result'][0];
        return $company;
    }

    public function getDeal(int $dealID)
    {
        $deal = $this->getBitrixEntity("crm.deal.get", $dealID, __FUNCTION__);
        return $deal;
    }

    public function getEntityTitle($entity) : string
    {
        return !empty($entity['NAME']) && !empty($entity['LAST_NAME'])
                ? "{$entity['NAME']} {$entity['LAST_NAME']}"
                : (
                    !empty($entity['TITLE'])
                    ? $entity['TITLE']
                    : ($entity['NAME'] ?? "")
                );
    }

    public function getPersonalContacts($profile, $type = 'PHONE')
    {
        $contacts = [];
        if($profile["HAS_{$type}"] === 'N')
        {
            return $contacts;
        }
        $this->bx24Logger->debug("getPersonalContacts - {$type}", [
            $type => $profile[$type],
        ]);
        foreach($profile[$type] as $contact)
        {
            $contacts[] = [
                'ENTITY_ID' => $profile['ID'],
                'ENTITY_TYPE_ID' => 3,
                'VALUE' => $contact['VALUE']
            ];
        }
        return $contacts;
    }

    public function getCompanyContacts($company, $type = 'PHONE'): array
    {
        $this->bx24Logger->debug("getCompanyPhones - entity", ['company' => $company]);

        $clercPhoneNumbers = [];;
        $companyId = intval($company['ID']);
        $contacts = $this->getBitrixEntity('crm.company.contact.items.get', $companyId, __FUNCTION__);
        if(count($contacts) == 0)
        {
            return $clercPhoneNumbers;
        }
        return $this->addContactsIntoList($contacts, $type, $clercPhoneNumbers, __FUNCTION__);
    }

    public function getCompanyContactsInfo($company, $type = 'PHONE'): array
    {
        $contacts = [];
        if($company && isset($company[$type]))
        {
            foreach($company[$type] as $idx => $contact)
            {
                $contacts[] = [
                    'ENTITY_ID' => $company['ID'],
                    'ENTITY_TYPE_ID' => 4,
                    'VALUE' => isset($company[$type][$idx]['VALUE'])? $company[$type][$idx]['VALUE']: ""
                ];
            }
            $this->bx24Logger->debug("getCompanyContactsInfo - contacts", ['contacts' => $contacts]);
        }
        return $contacts;
    }

    public function getDealData($dealId)
    {
        $arResult = [];

        $getDealCmd = $this->obBx24App->addBatchCall('crm.deal.get', ['id' => $dealId], function($result) use (&$arResult)
        {
            if($result['result'])
            {
                $arResult['deal'] = $result['result'];
            }
        });

        $this->obBx24App->addBatchCall('crm.company.get', ['id' => '$result[' . $getDealCmd . '][COMPANY_ID]'], function($result) use (&$arResult)
        {
            if($result['result'] && $result['result']['ID'] === $arResult['deal']['COMPANY_ID'])
            {
                $arResult['company'] = $result['result'];
            }
        });

        $this->obBx24App->addBatchCall('crm.contact.get', ['id' => '$result[' . $getDealCmd . '][CONTACT_ID]'], function($result) use (&$arResult)
        {
            if($result['result'] && $result['result']['ID'] === $arResult['deal']['CONTACT_ID'])
            {
                $arResult['contact'] = $result['result'];
            }
        });

        $this->obBx24App->processBatchCalls();

        $this->bx24Logger->debug('getDealData - result', ['$arResult' => $arResult]);

        return $arResult;
    }

    public function getDealCommunicationInfo($dealData, $contactTypes)
    {
        $contacts = [];

        if(isset($dealData['company']))
        {
            $company = $dealData['company'];
            foreach($contactTypes as $type)
            {
                $contacts[$type] = $this->getCompanyContactsInfo($company, $type);
            }
        }
        elseif(isset($dealData['contact']))
        {
            $contact = $dealData['contact'];
            foreach($contactTypes as $type)
            {
                $contacts[$type] = $this->getPersonalContacts($contact, $type);
            }
        }

        $this->bx24Logger->debug('getDealCommunicationInfo - contacts', ['contacts' => $contacts]);

        return $contacts;
    }

    private function addContactsIntoList(array $contacts, string $type, &$phonesList, string $function)
    {
        $contactIDs = array_column($contacts, 'CONTACT_ID');
        $entities = $this->fetchBitrixEntities('crm.contact.list', $contactIDs, $function . ' - ' . __FUNCTION__);
        foreach($entities as $profile)
        {
            $contacts = $this->getPersonalContacts($profile, $type);
            $phonesList = array_merge($phonesList, $contacts);
        }
        return $phonesList;
    }

    private function fetchBitrixEntities(string $listMethod, $id, string $function)
    {
        $parameters = [
            'filter' => [ "ID" => $id ],
            'select' => [ '*', 'PHONE', 'CONTACTS', 'UF_*' ]
        ];
        $entity = $this->obBx24App->call($listMethod, $parameters);
        $this->bx24Logger->debug("{$function} - {$listMethod}", [
            'parameters' => $parameters,
            'response' => $entity
        ]);
        return $entity['result'];
    }

    private function getBitrixEntity(string $method, $id, string $function)
    {
        $parameters = [ "ID" => $id ];
        $entity = $this->obBx24App->call($method, $parameters);
        $this->bx24Logger->debug("{$function} - {$method}", [
            'parameters' => $parameters,
            'response' => $entity
        ]);
        return $entity["result"];
    }

    #endsection

    public static function getActivityTypeAndName()
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

    private function getFileAttachArray(array $attaches) : array
    {
        $attachment = [];
        foreach($attaches as $file)
        {
            if ($file->getSize() == 0) {
                continue;
            }
            $tmpName = $file->getStream()->getMetadata('uri');
            $content = base64_encode(file_get_contents($tmpName));
            $this->bx24Logger->debug(__FUNCTION__ . ' - add file to attach', [
                'tmpFile' => $tmpName,
                'content' => mb_substr($content, 0, 20) . '...',
            ]);
            $attachment[] = ['fileData' => [$file->getClientFilename(), $content]];
        }
        return $attachment;
    }

    private function refreshTokens()
    {
        $oldAccessToken = $this->obBx24App->getAccessToken();
        $oldRefreshToken = $this->obBx24App->getRefreshToken();
        $tokensRefreshResult = $this->obBx24App->getNewAccessToken();
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

    private function saveFilesAndMakeAttach(array $files, $userID)
    {
        $baseFolder = "{$_SERVER['DOCUMENT_ROOT']}/webroot/files/";
        $appBaseURL = Configure::read('AppConfig.appBaseUrl');
        $baseUrl = (!$appBaseURL)
            ? Router::url('/files', true)
            : $appBaseURL . Router::url('/files', false);
        $attach = [];
        $j = 0;
        foreach($files as $i => $file)
        {
            if($file->getSize() == 0) {
                continue;
            }
            // Save in folder
            $origName = $file->getClientFileName();
            $parts = explode(".", $origName);
            $ext = array_pop($parts);
            $fileName = mb_substr(md5($origName . date(static::DATE_TIME_FORMAT) . $userID), -16) . '.' . $ext;
            $subFolder = date('Ymd') . bin2hex(random_bytes(6));
            $folder = $baseFolder . DS . $subFolder . DS;
            if (!file_exists($folder)) {
                mkdir($folder);
                chmod($folder, 655);
            }
            $file->moveTo($folder . DS . $fileName);

            // Make attach block
            $link = $baseUrl . DS . $subFolder  . DS. $fileName;
            $image = [
                [
                    'NAME' => $origName,
                    'LINK' => $link,
                    'PREVIEW' => $link,
                ]
            ];
            if (in_array(mb_convert_case($ext, MB_CASE_LOWER), ['png', 'jpg', 'gif'])) {
                list($width, $height) = getimagesize($folder . DS . $fileName);
                $image[0]['WIDTH'] = $width;
                $image[0]['HEIGHT'] = $height;
            }
            $attach[$j]["IMAGE"] = $image;
            $j++;
        }
        return $attach;
    }

    public function getContactWorkflowTemplates(): array
    {
        $arResult = [0 => __('Please choise ...')];

        $parameters = [
            'select' => [
                'ID', 'NAME'
            ],
            'filter' => [
                'MODULE_ID' => 'crm',
                'ENTITY' => 'CCrmDocumentContact'
            ]
        ];

        $result = $this->obBx24App->call('bizproc.workflow.template.list', $parameters);
        if($result['result'])
        {
            foreach($result['result'] as $workflow)
            {
                $arResult[$workflow['ID']] = $workflow['NAME'];
            }
        }

        $this->bx24Logger->debug(__FUNCTION__ . " - templates", [
            'parameters' => $parameters,
            'result' => $result
        ]);

        return $arResult;
    }

    public function startWorkflowForContact(int $templateId, int $contactId, array $templateParameters = [])
    {
        $arMethodParams = [
            'TEMPLATE_ID' => $templateId,
            'DOCUMENT_ID' => ['crm', 'CCrmDocumentContact', $contactId],
            'PARAMETERS' => $templateParameters
        ];

        $result = $this->obBx24App->call('bizproc.workflow.start', $arMethodParams);

        $this->bx24Logger->debug(__FUNCTION__ . " - start workflow", [
            'arMethodParams' => $arMethodParams,
            'result' => $result
        ]);

        return $result['result'] ?? 0;
    }

    public function searchActivitiesByTicketNumber($ticketNumber): array
    {
        $arResult = [];
        $parameters = [
            'filter' => [
                '%SUBJECT' => self::TICKET_PREFIX . $ticketNumber
            ],
            'order' => [
                'ID' => 'desc'
            ],
            'select' => ['ID', 'SUBJECT', 'DIRECTION', 'DESCRIPTION', 'DESCRIPTION_TYPE', 'CREATED']
        ];

        $result = $this->obBx24App->call('crm.activity.list', $parameters);

        if($result['result'])
        {
            $arResult = $result['result'];

            foreach($arResult as $i => $activity)
            {
                $arResult[$i]['CREATED'] = date(self::DATE_TIME_FORMAT, strtotime($activity['CREATED']));
            }
        }

        return $arResult;
    }
}
