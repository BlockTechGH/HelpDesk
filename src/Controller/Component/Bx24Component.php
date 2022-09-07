<?php
namespace App\Controller\Component;

use Bitrix24\Exceptions\Bitrix24TokenIsExpiredException;
use Monolog\Logger;
use Cake\Routing\Router;
use Cake\Core\Configure;
use Cake\Controller\Component;
use Monolog\Handler\StreamHandler;
use Bitrix24\Exceptions\Bitrix24ApiException;
use Bitrix24\Exceptions\Bitrix24SecurityException;

class Bx24Component extends Component
{

    private $controller = null;
    private $BitrixTokens = null;
    private $obBx24App = null;
    public $bx24Logger = null;

    const IM_CONNECTOR_URI = 'https://wa.me/';
    const HOUR = 3600;
    const ENTITY_TYPE_ID_LEAD = 1;
    const ENTITY_TYPE_ID_DEAL = 2;
    const ENTITY_TYPE_ID_CONTACT = 3;
    const ENTITY_TYPE_ID_COMPANY = 4;

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

    public function installConnector($placementList)
    {
        $postfix = Configure::read('AppConfig.itemsPostfix');
        $appBaseUrl = Configure::read('AppConfig.appBaseUrl');
        $wwwRoot = Configure::read('App.wwwRoot');
        $iconPath = $wwwRoot . "img/whatsapp-logo.svg";
        $handlerUrl = ($appBaseUrl)
            ? $appBaseUrl . Router::url([
                '_name' => 'oc_settings_interface'
            ], false)
            : Router::url([
            '_name' => 'oc_settings_interface'
        ], true);

        $arConnectorParams = [
            'ID' => 'BT_WHATSAPP' . (($postfix) ? "_" . $postfix : ''),
            'NAME' => __('BT Whatsapp') . (($postfix) ? " " . $postfix : ''),
            'ICON' => [
                'DATA_IMAGE' => 'data:image/svg+xml;base64,' . base64_encode(file_get_contents($iconPath)),
                'COLOR' => '#01e675',
                'SIZE' => '100%',
                'POSITION' => 'center',
            ],
            'ICON_DISABLED' => [
                'DATA_IMAGE' => 'data:image/svg+xml;base64,' . base64_encode(file_get_contents($iconPath)),
                'COLOR' => '#bcecd4',
                'SIZE' => '100%',
                'POSITION' => 'center',
            ],
            'PLACEMENT_HANDLER' => $handlerUrl,
        ];

        // we need unbind old ngrok handlers
        foreach($placementList as $placement)
        {
            if($placement['placement'] == 'SETTING_CONNECTOR')
            {
                $arParams = [
                    'PLACEMENT' => $placement['placement'],
                    'HANDLER' => $placement['handler']
                ];

                $this->obBx24App->addBatchCall('placement.unbind', $arParams, function($result) use ($arParams)
                {
                    $this->bx24Logger->debug('installConnector - placement.unbind', [
                        'arParams' => $arParams,
                        'result' => $result
                    ]);
                });
            }
        }

        $this->obBx24App->addBatchCall('imconnector.register', $arConnectorParams, function($result) use ($arConnectorParams)
        {
            $this->bx24Logger->debug('installConnector - imconnector.register', [
                'arParams' => $arConnectorParams,
                'result' => $result
            ]);
        });

        $this->obBx24App->processBatchCalls();
    }

    public function getInstalledData(): array
    {
        $arData = [
            'placementList' => [],
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

    public function getCurrentUser()
    {
        $parameters = [];
        $result = $this->obBx24App->call('user.current', $parameters);
        $this->bx24Logger->debug('getCurrentUser - user.current', [
            'arrParams' => $parameters,
            'result' => $result
        ]);
        return $result['result'];
    }

    public function activateConnector(string $phoneNumber, string $widgetName, string $options)
    {
        $options = json_decode($options, true);
        $connectorId = $options['CONNECTOR'];
        $line = intVal($options['LINE']);
        $request = [
            'CONNECTOR' => $connectorId,
            'LINE' => $line,
            'ACTIVE' => intVal($options['ACTIVE_STATUS']),
        ];
        $this->obBx24App->addBatchCall('imconnector.activate', $request, function($result) use ($request)
        {
            $this->bx24Logger->debug('activateConnector - imconnector.activate', [
                'request' => $request,
                'response' => $result
            ]);
        });

        //add data widget
        $widgetUri = self::IM_CONNECTOR_URI.  $phoneNumber;
        if (!empty($widgetName))
        {
            $request = [
                'CONNECTOR' => $connectorId,
                'LINE' => intVal($options['LINE']),
                'DATA' => [
                    'id' => "{$connectorId}line{$line}",//
                    'url_im' => $widgetUri,
                    'name' => $widgetName
                ],
            ];

            $this->obBx24App->addBatchCall('imconnector.connector.data.set', $request, function($resultWidgetData) use ($request)
            {
                $this->bx24Logger->debug('activateConnector - imconnector.connector.data.set', [
                    'request' => $request,
                    'response' => $resultWidgetData
                ]);
            });
        }
        $this->obBx24App->processBatchCalls(1);
        return $connectorId;
    }

    public function createCRMActivity(
        string $messageID, // ID of Kaleyra message, get from response
        string $activitySubject, // Header of activity
        string $description,     // Body of activity block
        array $crmEntity,
        array $contact,
        $responsibleUserId,
        bool $statusOk = true,
        bool $createdOnChat = true
    )
    {
        $postfix = Configure::read('AppConfig.itemsPostfix');
        $typeName = 'BT_WHATSAPP' . (($postfix) ? "_" . strtoupper($postfix) : '');
        $this->bx24Logger->debug('createCRMActivity - params', [
            'arguments' => [
                'crmEntity' => $crmEntity,
                'contact' => $contact,
                'Direction.out' => $createdOnChat
            ],
        ]);
        $parameters = [
            'fields' => [
                'OWNER_ID' => (int)$crmEntity['ID'],
                'OWNER_TYPE_ID' => $crmEntity['TYPE_ID'],
                'PROVIDER_ID' => 'REST_APP',
                'PROVIDER_TYPE_ID' => $typeName,
                'PROVIDER_DATA' => $messageID,
                'DIRECTION' => intval($createdOnChat) + 1,
                'SUBJECT' => $activitySubject,
                'DESCRIPTION' => $description,
                'RESPONSIBLE_ID' => $responsibleUserId,
                'COMPLETED' => 'Y',
                'COMMUNICATIONS' => [ [
                    'ENTITY_ID' => (int)$contact['ID'],
                    'ENTITY_TYPE_ID' => $crmEntity['TYPE_ID'],
                    'VALUE' => $contact['phone']
                ] ],
            ]
        ];
        if (!$statusOk) {
            $parameters['fields']['STATUS'] = 'failed';
        }
        $result = $this->obBx24App->call('crm.activity.add', $parameters);
        $this->bx24Logger->debug('createCrmActivity - crm.activity.add', [
            'arrParams' => $parameters,
            'result' => $result,
        ]);
        return $result;
    }

    public function findCRMActivityByMessageId(string $kaleyraMessageID)
    {
        $parameters = [
           'filter' => [
               'PROVIDER_DATA' => $kaleyraMessageID
           ],
           'select' => ['ID', 'DESCRIPTION']
        ];
        $result = $this->obBx24App->call('crm.activity.list', $parameters);
        $this->bx24Logger->debug('findCRMActivityByMessageID - crm.activity.list', [
            'arrParameters' => $parameters,
            'response' => $result
        ]);
        return $result['result'][0];
    }

    public function updateCRMActivityDescription(int $activityId, string $description)
    {
        $parameters = [
            'id' => $activityId,
            'fields' => [
                'DESCRIPTION' => $description
            ]
        ];
        $this->obBx24App->addBatchCall('crm.activity.update', $parameters, function ($result) use ($parameters) {
           $this->bx24Logger->debug('updateCRMActivityDescription - crm.activity.update', [
               'arrParameters' => $parameters,
               'response' => $result
           ]);
        });
        $this->obBx24App->processBatchCalls();
    }

    public function installApplicationData($arInstalledData)
    {
        $postfix = Configure::read('AppConfig.itemsPostfix');
        $wwwRoot = Configure::read('App.wwwRoot');
        $iconFile = "whatsapp-logo-activity.png";
        $iconPath = $wwwRoot . "img/" . $iconFile;
        $appBaseUrl = Configure::read('AppConfig.appBaseUrl');


        // add activity type
        if(!count($arInstalledData['activityTypeList']))
        {
            $arActivityTypeParams['fields'] = [
                'TYPE_ID' => 'BT_WHATSAPP' . (($postfix) ? "_" . strtoupper($postfix) : ''),
                'NAME' => __('BT Whatsapp') . (($postfix) ? " " . $postfix : ''),
                'ICON_FILE' => [
                    $iconFile,
                    base64_encode(file_get_contents($iconPath))
                ]
            ];

            $this->obBx24App->addBatchCall('crm.activity.type.add', $arActivityTypeParams, function($result) use ($arActivityTypeParams)
            {
                $this->bx24Logger->debug('installApplicationData - crm.activity.type.add', [
                    'arParams' => $arActivityTypeParams,
                    'result' => $result
                ]);
            });
        }


        // add placements in crm card
        $arNeedPlacements = [
            'CRM_LEAD_DETAIL_ACTIVITY',
            'CRM_DEAL_DETAIL_ACTIVITY',
            'CRM_CONTACT_DETAIL_ACTIVITY',
            'CRM_COMPANY_DETAIL_ACTIVITY'
        ];

        foreach($arInstalledData['placementList'] as $placement)
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

        foreach($arNeedPlacements as $placement)
        {
            $arBindParams = [
                'PLACEMENT' => $placement,
                'HANDLER' => ($appBaseUrl)
                    ? $appBaseUrl . Router::url([
                            '_name' => 'crm_interface'
                        ], false)
                    : Router::url([
                            '_name' => 'crm_interface'
                        ], true),
                'LANG_ALL' => [
                    'en' => [
                        'TITLE' => __('BT Whatsapp') . (($postfix) ? " " . $postfix : '')
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


        // Bind/UnBind on OnImConnectorMessageAdd, OnImConnectorLineDelete, OnImConnectorStatusDelete
        $arNeedEvents = [
            'ONIMCONNECTORMESSAGEADD',
            'ONIMCONNECTORLINEDELETE',
            'ONIMCONNECTORSTATUSDELETE'
        ];

        foreach($arInstalledData['eventList'] as $event)
        {
            // unbind old events firts
            if(in_array($event['event'], $arNeedEvents))
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

        foreach($arNeedEvents as $event)
        {
            if($event == 'ONIMCONNECTORMESSAGEADD')
            {
                $arBindEventParams = [
                    'event' => $event,
                    'handler' => ($appBaseUrl)
                        ? $appBaseUrl . Router::url([
                                '_name' => 'oc_message_handler'
                            ], false)
                        : Router::url([
                                '_name' => 'oc_message_handler'
                            ], true),
                ];

                $this->obBx24App->addBatchCall('event.bind', $arBindEventParams, function($result) use ($arBindEventParams)
                {
                    $this->bx24Logger->debug('installApplicationData - event.bind', [
                        'arParams' => $arBindEventParams,
                        'result' => $result
                    ]);
                });
            } else {
                $arBindEventParams = [
                    'event' => $event,
                    'handler' => ($appBaseUrl)
                        ? $appBaseUrl . Router::url([
                                '_name' => 'oc_event_handler'
                            ], false)
                        : Router::url([
                                '_name' => 'oc_event_handler'
                            ], true),
                ];

                $this->obBx24App->addBatchCall('event.bind', $arBindEventParams, function($result) use ($arBindEventParams)
                {
                    $this->bx24Logger->debug('installApplicationData - event.bind', [
                        'arParams' => $arBindEventParams,
                        'result' => $result
                    ]);
                });
            }
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

    public function sendMessageToBxChat(int $line, array $arrKaleyraMessages)
    {
        $arrParameters = [
            'CONNECTOR' =>  $this->getConnectorID(),
            'LINE' => $line,
            'MESSAGES' => array_map(function ($arrKaleyraMessage) {
                return $this->makeBxMessageFromKaleyraMessage($arrKaleyraMessage);
            }, $arrKaleyraMessages),
        ];
        $this->bx24Logger->debug('imconnector.send.messages', $arrParameters);
        $arrResponse = $this->obBx24App->call('imconnector.send.messages', $arrParameters);
        $this->bx24Logger->debug('sendMessageToBxChat - imconnector.send.messages', [
            'parameters' => $arrParameters,
            'response' => $arrResponse
        ]);
        return $arrResponse;
    }

    public function sendSystemMessagesToBxChat(array $chats, string $prefix = "")
    {
        $prefix = $prefix == "" ? __("An error occurred while sending the message: ") : $prefix;
        foreach($chats as $chatId => $message)
        {
            $this->bx24Logger->debug("Message from {$chatId}", $message);
            $arrParameters = [
                "DIALOG_ID" => "chat{$chatId}",
                "MESSAGE" => trim("{$prefix} {$message}"),
                "SYSTEM" => "Y",
            ];
            $this->obBx24App->addBatchCall('im.message.add', $arrParameters, function ($response) use ($arrParameters) {
                $this->bx24Logger->debug("sendSystemMessagesToBxChat - im.message.add", [
                    'parameters' => $arrParameters,
                    'response' => $response
                ]);
            });
        }
        $this->obBx24App->processBatchCalls();
    }

    public function setMessageOuterIDs(array $bitrixMessages, array $foreignMessages) : array
    {
        foreach($bitrixMessages as $n => $MESSAGE)
        {
            $foreignMessage = $foreignMessages[$n];
            if(empty($foreignMessage['id']))
            {
                continue;
            }
            // Bitrix message with multimedia can contain more than one file ad text.
            // But Kaleyra-WhatsApp message can contain text xor one file only.
            // KaleyraComponent->akeKaleyraMessagesFromBx24Messages() create a more than one message with one bx_id param in source array at this case.
            // We need set only one ID of Bitrix message.
            if($n > 0 && $foreignMessages[$n-1]['bx_id'] == $foreignMessage['bx_id'])
            {
                continue;
            }
            $bitrixMessages[$n]['message']['id'] = $foreignMessages['id'];
        }
        $this->bx24Logger->debug('setMessageOuterIDs', [
            'bitrix' => $bitrixMessages,
            'kaleyra' => $foreignMessages
        ]);
        return $bitrixMessages;
    }

    public function markMessagesAs(string $connector, int $line, array $messages, string $status = 'delivered')
    {
        $message = $messages[0];
        $arrParameters = [
            'LINE' => $line,
            'CONNECTOR' => $connector,
            'MESSAGES' => [
                [
                    'im' => $message['im'],
                    'chat' => ['id' => $message['chat']['id'] ],
                    'message' => [  ]
                ]
            ]
        ];

        foreach($messages as $i => $message)
        {
            // Use first message as pattern
            if($i > 0)
            {
                $arrParameters['MESSAGES'][$i] = array_merge([], $arrParameters['MESSAGES'][0]);
            }
            if(empty($message['message']['id']))
            {
                unset($messages[$i]);
                continue;
            }
            $arrParameters['MESSAGES'][$i]['message'][] = ['id' => $message['message']['id']];
        }

        $method = null;
        switch($status)
        {
            case "delivered": $method = 'imconnector.send.status.delivery'; break;
            case "read": $method = 'imconnector.send.status.reading'; break;
        }
        if(!$method)
        {
            $this->bx24Logger->debug("Status of message '{$status}' is not important for Bitrix24");
            return;
        }
        $response = $this->obBx24App->call($method, $arrParameters);
        $this->bx24Logger->debug("markMessageAs({$status}) - {$method}", [
            'parameters' => $arrParameters,
            'result' => $response
        ]);
    }

    public function getContact(int $contactID)
    {
        $contact = $this->getBitrixEntity("crm.contact.get", $contactID, __FUNCTION__);
        $contact['TYPE_ID'] = static::ENTITY_TYPE_ID_CONTACT;
        return $contact;
    }

    public function getLead(int $leadID)
    {
        $lead = $this->getBitrixEntity('crm.lead.get', $leadID, __FUNCTION__);
        $lead['TYPE_ID'] = static::ENTITY_TYPE_ID_LEAD;
        return $lead;
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
        if (!!$company) {
            $company['TYPE_ID'] = self::ENTITY_TYPE_ID_COMPANY;
        }
        return $company;
        $company = $this->getBitrixEntity('crm.company.get', $companyID, __FUNCTION__);
        $company['TYPE_ID'] = self::ENTITY_TYPE_ID_COMPANY;
        return $company;
    }

    public function getDeal(int $dealID)
    {
        $deal = $this->getBitrixEntity('crm.deal.get', $dealID, __FUNCTION__);
        $deal['TYPE_ID'] = self::ENTITY_TYPE_ID_DEAL;
        return $deal;
    }

    public function getEntityTitle($entity, bool $isLead = false) : string
    {
        if($isLead)
        {
            return !empty($entity['NAME']) && !empty($entity['LAST_NAME'])
                ? "{$entity['NAME']} {$entity['LAST_NAME']}"
                : $entity['TITLE'];
        } else {
            return $entity['TITLE'] ?? "{$entity['NAME']} {$entity['LAST_NAME']}";
        }
    }

    public function getPersonalPhones($profile) : array
    {
        $this->bx24Logger->debug("getPersonalPhones - entity", $profile);

        $phones = [];
        if($profile['HAS_PHONE'] === 'N')
        {
            return $phones;
        }
        $title = implode(' ', [$profile['NAME'], $profile['SECOND_NAME'], $profile['LAST_NAME']]);
        foreach($profile['PHONE'] as $phoneObject)
        {
            $phones[$phoneObject['VALUE']] = [
                'type' => $phoneObject['VALUE_TYPE'],
                'title' => "{$phoneObject['VALUE']}",
                'ID' => $profile['ID'],
                'TITLE' => $title,
                'NAME' => $profile['NAME'],
                'LAST_NAME' => $profile['LAST_NAME']
            ];
        }
        return $phones;
    }

    public function getLeadPhones($lead): array
    {
        $this->bx24Logger->debug("getLeadPhones - entity", $lead);

        $phones = [];
        $title = $lead['TITLE'];
        foreach($lead['PHONE'] as $phoneObject)
        {
            $phones[$phoneObject['VALUE']] = [
                'type' => $phoneObject['VALUE_TYPE'],
                'title' => "{$phoneObject['VALUE']}",
                'ID' => $lead['ID'],
                'TITLE' => $title,
                'NAME' => $lead['NAME'],
                'LAST_NAME' => $lead['LAST_NAME']
            ];
        }
        return $phones;
    }

    public function getDealPhones($deal) : array
    {
        $this->bx24Logger->debug("getDealPhones - entity", $deal);

        $contactPhones = [];
        $dealId = intval($deal["ID"]);
        $dealContacts = $this->getBitrixEntity('crm.deal.contact.items.get', $dealId, __FUNCTION__);
        if(count($dealContacts) > 0)
        {
            $this->addContactPhonesIntoList($dealContacts, $contactPhones, __FUNCTION__);
        }

        $stopList = array_column($contactPhones, "ID");
        $companyID = intval($deal['COMPANY_ID']);
        if($companyID > 0)
        {
            usleep(10000);
            $this->bx24Logger->debug("getDealPhones(deal#{$dealId}) - related with company#{$companyID}");
            $company = $this->getCompany($companyID);
            if (!$company) {
                $this->bx24Logger->debug("Company not found", [
                    'company.ID' => $companyID
                ]);
                return $contactPhones;
            }
            $companyPhones = $this->getCompanyPhones($company);
            $this->bx24Logger->debug("getDealPhones(deal#{$dealId}) - getCompanyPhones(company#{$companyID})", [
                'arrDealContacts' => $contactPhones,
                'arrCompanyContacts' => $companyPhones
            ]);
            foreach ($companyPhones as $i => $companyPhone) {
                if (in_array($companyPhone['ID'], $stopList)) {
                    unset($companyPhones[$i]);
                }
            }
            $contactPhones = array_merge($contactPhones, $companyPhones);
        }

        return $contactPhones;
    }

    public function getCompanyPhones($company): array
    {
        $this->bx24Logger->debug("getCompanyPhones - entity", ['company' => $company]);

        $clercPhoneNumbers = [];;
        $companyId = intval($company['ID']);
        $contacts = $this->getBitrixEntity('crm.company.contact.items.get', $companyId, __FUNCTION__);
        if(count($contacts) == 0)
        {
            return $clercPhoneNumbers;
        }
        return $this->addContactPhonesIntoList($contacts, $clercPhoneNumbers, __FUNCTION__);
    }

    public function getConnectorID() : string
    {
        $postfix = Configure::read('AppConfig.itemsPostfix');
        return 'BT_WHATSAPP' . (($postfix) ? "_" . $postfix : '');
    }

    private function addContactPhonesIntoList(array $contacts, &$phonesList, string $function)
    {
        $contactIDs = array_column($contacts, 'CONTACT_ID');
        $entities = $this->fetchBitrixEntities('crm.contact.list', $contactIDs, $function . ' - ' . __FUNCTION__);
        foreach($entities as $profile)
        {
            if($profile['HAS_PHONE'] === 'N')
            {
                return $phonesList;
            }
            $title = implode(' ', [$profile['NAME'], $profile['SECOND_NAME'], $profile['LAST_NAME']]);
            foreach($profile['PHONE'] ?? [] as $phoneObject)
            {
                $phonesList[$this->clearPhoneNumber($phoneObject['VALUE'])] = array_merge($profile, [
                    'type' => $phoneObject['VALUE_TYPE'],
                    'title' => "{$phoneObject['VALUE']} ($title)",
                    'TITLE' => $title
                ]);
            }
        }
        return $phonesList;
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

    private function clearPhoneNumber(string $phone): string
    {
        return mb_ereg_replace('\D', '', $phone);
    }

    private function makeBxMessageFromKaleyraMessage(array $kaleyraMessage) : array
    {
        $bitrixMessage = [
            'chat' => [
                'id' => $kaleyraMessage['from'],
            ],
            'user' => [
                'id' => $kaleyraMessage['from'],
                'name' => $kaleyraMessage['profile']['name'],
                'phone' => $kaleyraMessage['from'],
            ],
            'message' => array(
                'id' => $kaleyraMessage['id'],
                'date' => $kaleyraMessage['timestamp'],
            ),
        ];

        if ($kaleyraMessage['type'] == 'text')
        {
            $bitrixMessage['message']['text'] = $kaleyraMessage['text']['body'];
        } elseif($kaleyraMessage['type'] == 'button') {
            $bitrixMessage['message']['text'] = $kaleyraMessage['caption'];
        } else {
            $bitrixMessage['message']['files'] = [
                [ 'url' => $kaleyraMessage['url'] ],
            ];
        }

        return $bitrixMessage;
    }

    private function refreshTokens()
    {
        $oldAccessToken = $this->obBx24App->getAccessToken();
        $oldRefreshToken = $this->obBx24App->getRefreshToken();
        $tokensRefreshResult = $this->obBx24App->getNewAccessToken();
        $this->bx24Logger->debug('refreshTokens - getNewAccessToken - result', [
            '$tokensRefreshResult' => $tokensRefreshResult
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
