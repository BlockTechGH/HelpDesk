<?php
declare(strict_types=1);

namespace App\Controller;

use App\Controller\Component\Bx24Component;
use App\Model\Table\HelpdeskOptionsTable;
use App\Model\Table\TicketStatusesTable;
use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\Event\EventInterface;
use Cake\Http\Response;
use Cake\Routing\Router;
use Cake\I18n\FrozenTime;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Cake\Mailer\Mailer;

class BitrixController extends AppController
{
    private $Options;
    private $Categories;
    private $Statuses;
    private $BitrixTokens;
    private $Tickets;

    private $placement;
    private $ticket = null;
    private $messages = [];
    private const SEPARATOR = '|';

    public function initialize() : void
    {
        parent::initialize();
        $event = $this->request->getData('event');
        $auth = $this->request->getData('auth');

        if($event && $auth)
        {
            $this->isAccessFromBitrix = true;
            $this->memberId = $auth['member_id'] ?? "";
            $this->authId = $auth['access_token'] ?? "";
            $this->refreshId = $auth['refresh_token'] ?? "";
            $this->authExpires = $auth['expires_in'] ?? "";
            $this->domain = $auth['domain'];
        } else {
            $this->authId = $this->request->getData('AUTH_ID') ?? '';
            $this->refreshId = $this->request->getData('REFRESH_ID') ?? '';
            $this->authExpires = (int)($this->request->getData('AUTH_EXPIRES')) ?? '';
            $this->memberId = $this->request->getData('member_id');
            $this->domain = $this->request->getQuery('DOMAIN');
            $this->isAccessFromBitrix = $this->authId && $this->memberId && $this->domain;
        }
    }

    public function beforeFilter(EventInterface $event)
    {
        parent::beforeFilter($event);
        // we need redirect if request not form Bitrix24
        if(!$this->isAccessFromBitrix)
        {
            return $this->redirect([
                '_name' => 'home',
            ]);
        }

        if ($this->memberId && !($this->refreshId && $this->authId && $this->authExpires)) {
            $this->BitrixTokens = $this->getTableLocator()->get('BitrixTokens');
            $tokenRecord = $this->BitrixTokens->getTokenObjectByMemberId($this->memberId);
            $this->authId = $tokenRecord['auth_id'];
            $this->refreshId = $tokenRecord['refresh_id'];
            $this->authExpires = (int)$tokenRecord['auth_expires'];
            $this->domain = $tokenRecord['domain'];
        }
        $this->loadComponent('Bx24');
        $this->Options = $this->getTableLocator()->get('HelpdeskOptions');
        $this->Statuses = $this->getTableLocator()->get('TicketStatuses');
        $this->Categories = $this->getTableLocator()->get('TicketCategories');
        $this->IncidentCategories = $this->getTableLocator()->get('IncidentCategories');
        $this->Tickets = $this->getTableLocator()->get('Tickets');
        $this->TicketBindings = $this->getTableLocator()->get('TicketBindings');
        $this->TicketHistory = $this->getTableLocator()->get('TicketHistory');
        $this->EventTypes = $this->getTableLocator()->get('EventTypes');
        $logFile = Configure::read('AppConfig.LogsFilePath') . DS . 'bitrix_controller.log';
        $this->BxControllerLogger = new Logger('BitrixController');
        $this->BxControllerLogger->pushHandler(new StreamHandler($logFile, Logger::DEBUG));

        $action = $this->request->getParam('action');
        $this->placement = json_decode($this->request->getData('PLACEMENT_OPTIONS') ?? "", true);
        unset($this->placement['subject']);
        $answer = $this->request->getData('answer');
        $activity_id = $this->request->getData('activity_id');

        // hidden fields from app installation
        $this->set('required', [
            'AUTH_ID' => $this->authId,
            'AUTH_EXPIRES' => $this->authExpires,
            'REFRESH_ID'=> $this->refreshId,
            'member_id' => $this->memberId,
            'PLACEMENT_OPTIONS' => json_encode($this->placement),
        ]);
        $this->set('memberId', $this->memberId);
        $this->set('domain', $this->domain);
        $this->set('PLACEMENT_OPTIONS', $this->placement);
        $this->set('ajax', $this->getUrlOf('crm_settings_interface', $this->domain));
        $this->set('get_violations_url', $this->getUrlOf('get_violations', $this->domain));

        // subscribe on events
        $this->BxControllerLogger->debug(__FUNCTION__ . ' - subscribed', [
            'Ticket.statusChanged', 'Ticket.receivingCustomerResponse', 'Ticket.created'
        ]);
        $eventManager = $this->getEventManager();
        $eventManager->on('Ticket.statusChanged', [$this, 'handleTicketStatusChange']);
        $eventManager->on('Ticket.receivingCustomerResponse', [$this, 'handleReceivingCustomerResponse']);
        $eventManager->on('Ticket.created', [$this, 'handleTicketCreated']);
        $eventManager->on('Ticket.resolutionAdded', [$this, 'handleResolutionAdded']);

        if ($action == 'displaySettingsInterface')
        {
            $this->options = $this->Options->getSettingsFor($this->memberId);
            $this->statuses = $this->Statuses->getStatusesFor($this->memberId);
            $this->categories = $this->Categories->getCategoriesFor($this->memberId);
            $this->incidentCategories = $this->IncidentCategories->getCategoriesFor($this->memberId);

            $this->set('options', $this->options);
            $this->set('statuses', $this->statuses);
            $this->set('categories', $this->categories);
            $this->set('incidentCategories', $this->incidentCategories);
            $this->set('tickets', []);

            if (isset($this->placement['activity_id'])) {
                /*
                 * activity card
                 */
                $currentUser = $this->Bx24->getCurrentUser();

                // handle add resolution
                $do = $this->request->getData('do');
                if($do && $do === 'addResolution')
                {
                    $data = $this->request->getParsedBody();
                    return $this->addResolution($data, $currentUser);
                }

                // handle add or delete files
                if($do && ($do === 'addFiles' || $do === 'deleteFile'))
                {
                    $data = $this->request->getParsedBody();
                    return $this->handleAddOrDeleteFiles($data, $currentUser);
                }

                if($do && $do === 'assignNewDeal')
                {
                    $data = $this->request->getParsedBody();
                    return $this->assignNewDeal($data, $currentUser);
                }

                $this->ticket = $this->Tickets->getByActivityIdAndMemberId($this->placement['activity_id'], $this->memberId);

                if($this->ticket && $this->ticket['resolutions'])
                {
                    $resolutions = $this->ticket['resolutions'];
                    $userIds = array_unique(array_map(function($resolution)
                    {
                        return $resolution['author_id'];
                    }, array_values($resolutions)));
                    $arRowAuthorData = $this->Bx24->getUserById($userIds);
                    $arAuthorData = [];

                    foreach($arRowAuthorData as $user)
                    {
                        $arAuthorData[$user['ID']] = $user;
                    }

                    foreach($resolutions as $i => $resolution)
                    {
                        $user = $arAuthorData[$resolution['author_id']];
                        $resolutions[$i]['fullName'] = implode(' ', [$user['NAME'], $user['LAST_NAME']]);
                        $resolutions[$i]['formattedTime'] = $resolution->created->format(Bx24Component::DATE_TIME_FORMAT);
                        $resolutions[$i]['formattedText'] = str_replace(PHP_EOL, '<br>', $resolutions[$i]['text']);
                    }

                    $this->set('resolutions', $resolutions);
                    unset($this->ticket['resolutions']);
                } else {
                    $this->set('resolutions', []);
                }

                if ($this->ticket) {
                    $this->ticket->created = $this->ticket->created->format(Bx24Component::DATE_TIME_FORMAT);
                } else {
                    // Delete some records from 'tickets' table.
                    return $this->redirect([
                        '_name' => 'home'
                    ]);
                }

                $ticketAttributes = null;
                $source = null;
                $activityId = $this->ticket ? $this->ticket->action_id : $this->placement['activity_id'];
                $sourceId = $this->ticket ? $this->ticket->source_id : null;
                $activitiesId = [$activityId];
                $ticketClass = $this->Bx24->getActivityTypeAndName();
                $this->set('ticketActivityType', $ticketClass['TYPE_ID']);
                if ($sourceId) {
                    $activitiesId[] = $sourceId;
                }

                $activities = $this->Bx24->getActivities($activitiesId);
                $this->BxControllerLogger->debug(__FUNCTION__ . ' - activities', [
                    'source' => $sourceId,
                    'ticket' => $activityId,
                    'found' => $activities,
                ]);
                $ticketActivity = $activities[$activityId];
                $sourceActivity = $sourceId ? $activities[$sourceId] : $ticketActivity;
                if($ticketActivity)
                {
                    $ticketAttributes = $this->Bx24->getOneTicketAttributes($ticketActivity);
                    if($ticketAttributes)
                    {
                        $uid = $ticketAttributes['responsible'];
                        $createdBy = $ticketAttributes['createdBy'];
                        $usersAttributes = $this->Bx24->getUsersAttributes([$uid, $createdBy]);
                        $ticketAttributes['responsible'] = $usersAttributes[$uid];
                        $ticketAttributes['createdBy'] = $usersAttributes[$createdBy];
                    }
                }

                if($sourceActivity)
                {
                    $source = $ticketAttributes && $this->ticket ? $this->Bx24->getOneTicketAttributes($sourceActivity) : null;
                    if($source)
                    {
                        $uid = $source['responsible'];
                        $source['responsible'] = $this->Bx24->getUsersAttributes([$uid])[$uid];
                    }
                }

                if($this->ticket['bitrix_users'])
                {
                    $bitrixUsers = $this->Bx24->getBitrixUsersForTicket(json_decode($this->ticket['bitrix_users']));
                } else {
                    $bitrixUsers = [];
                }

                $this->set('dialogId', '');
                if($this->ticket && $this->ticket->source_type_id == Bx24Component::PROVIDER_OPEN_LINES && !$source['text'])
                {
                    $openChannelIdAndMessage = $this->Bx24->getFirstMessageInOpenChannelChat($source);
                    $this->set('dialogId', $openChannelIdAndMessage['dialogId']);
                    $firstMessage = $openChannelIdAndMessage['message'];
                    $source['text'] = $firstMessage['text'];
                }

                $arHistoryActivities = [];
                // get history for tickets from CALL, EMAIL, MANUALLY
                if($this->ticket && $this->ticket->source_type_id != Bx24Component::PROVIDER_OPEN_LINES)
                {
                    $arHistoryActivities = $this->Bx24->searchActivitiesByTicketNumber($this->ticket['id']);
                }

                foreach($arHistoryActivities as $i => $activity)
                {
                    if(isset($activity['FILES']) && $activity['FILES'])
                    {
                        foreach($activity['FILES'] as $j => $file)
                        {
                            $fileName = $this->getFileNameByUrlHeaders($file['url']);
                            if($fileName)
                            {
                                $arHistoryActivities[$i]['FILES'][$j]['fileName'] = $fileName;
                            }
                        }
                    }
                }

                $this->set('arHistoryActivities', $arHistoryActivities);

                $source['text'] = str_replace(PHP_EOL, '<br>', $source['text']);

                $this->set('ticket', $this->ticket);
                if (isset($this->placement['answer'])) {
                    $this->set('subject', str_replace("'", "\'", $ticketAttributes['subject']));
                    return $this->sendFeedback($answer ?? true, $currentUser, $ticketAttributes);
                } elseif((isset($this->placement['activity_id'])
                    && $this->placement['action'] == 'view_activity'))
                {
                    $this->ticketAttributes = $ticketAttributes;
                    if(!!$answer) {
                        $this->set('subject', $ticketAttributes['subject']);
                        return $this->sendFeedback($answer, $currentUser, $ticketAttributes);
                    }
                    if(!!$activity_id)
                    {
                        $set = $this->request->getData('set', false);
                        $this->Bx24->setCompleteStatus($activity_id, !!$set);
                        $ticket = $this->Tickets->getByActivityIdAndMemberId($activity_id, $this->memberId)->toArray();
                        $status = $this->Statuses->getFirstStatusForMemberTickets(
                            $this->memberId,
                            !!$set ? TicketStatusesTable::MARK_STARTABLE : TicketStatusesTable::MARK_FINAL
                        );

                        // send event Ticket Changed Status
                        $event = new Event('Ticket.statusChanged', $this, [
                            'ticket' => $ticket,
                            'status' => $status->name
                        ]);
                        $this->getEventManager()->dispatch($event);

                        $ticket = $this->Tickets->editTicket($ticket['id'], $status->id, $ticket['category_id'], $this->memberId, $ticket['bitrix_users'], $ticket['incident_category_id']);

                        return new Response(['body' => json_encode(['status' => $ticket['status_id']])]);
                    }
                    $this->BxControllerLogger->debug(__FUNCTION__ . ' - customer', $ticketAttributes['customer']);
                    $this->set('bitrixUsers', $bitrixUsers);
                    $this->set('ticketAttributes', $ticketAttributes);
                    $this->set('source', $source);
                    return $this->displayTicketCard($currentUser);
                }
            } else {
                $arTemplates = $this->Bx24->getEntityWorkflowTemplates();
                $arContactWorkflowTemplates = $arTemplates['contact'];
                $arCompanyWorkflowTemplates = $arTemplates['company'];
                $arDealWorkflowTemplates = $arTemplates['deal'];
                $this->set('arContactWorkflowTemplates', $arContactWorkflowTemplates);
                $this->set('arCompanyWorkflowTemplates', $arCompanyWorkflowTemplates);
                $this->set('arDealWorkflowTemplates', $arDealWorkflowTemplates);
            }
        }
    }

    public function displaySettingsInterface()
    {
        $data = $this->request->getParsedBody();

        $flashOptions = [
            'params' => [
                'dismissible' => true,
            ]
        ];

        if(isset($data['saveSettings']))
        {
            $this->options = $this->saveSettings($data);
            $this->Flash->success(__("All options saved"), $flashOptions);
            $arNotSourceSettings = $this->Options->getNotSourceSettingsFor($this->memberId);
            $this->options = array_merge($this->options, $arNotSourceSettings);
            $this->set('options', $this->options);
        } elseif(isset($data['category'])) {
            $category = $this->Categories->editCategory(
                $data['category']['id'] ?? 0,
                $data['category']['name'],
                $this->memberId,
                (bool)$data['category']['active']
            );
            return new Response(['body' => json_encode($category)]);
        } elseif(isset($data['incidentCategory'])) {
            $category = $this->IncidentCategories->editCategory(
                $data['incidentCategory']['id'] ?? 0,
                $data['incidentCategory']['name'],
                $this->memberId,
                (bool)$data['incidentCategory']['active']
            );
            return new Response(['body' => json_encode($category)]);
        }
        elseif(isset($data['saveSLASettings']))
        {
            $this->BxControllerLogger->debug(__FUNCTION__ . ' - save sla settings', ['data' => $data]);

            $arOptions[] = [
                'member_id' => $this->memberId,
                'opt' => 'sla_settings',
                'value' => serialize($data['settings'])
            ];

            $saveResult = $this->Options->updateOptions($arOptions);

            $this->BxControllerLogger->debug(__FUNCTION__ . ' - save result', [
                'result' => $saveResult,
                'value_len' => strlen(serialize($data['settings'])),
                'arOptions' => $arOptions
            ]);

            $result = [
               'error' => count($saveResult) ? false : true
            ];

            return new Response(['body' => json_encode($result)]);
        }
        elseif(isset($data['saveNotificationSettings']) && $data['selectValues'])
        {
            // save settings here
            $arOptions = [];
            foreach($data['selectValues'] as $name => $value)
            {
                $arOptions[] = [
                    'member_id' => $this->memberId,
                    'opt' => $name,
                    'value' => $value
                ];
            }

            $saveResult = $this->Options->updateOptions($arOptions);

            $result = [
               'error' => count($saveResult) ? false : true,
            ];

            return new Response(['body' => json_encode($result)]);
        } elseif(isset($data['ticket_status'])) {
            $mark = (int)$data['ticket_status']['mark'];
            $color = $data['ticket_status']['color'];
            if ($mark > 0) {
                $this->Statuses->flushMarks($mark);
            }
            try {
                $this->Statuses->editStatus(
                    $data['ticket_status']['id'],
                    $data['ticket_status']['name'],
                    $this->memberId,
                    (bool)$data['ticket_status']['active'],
                    $mark,
                    $color
                );
            } catch(\Exception $event)
            {
                $this->BxControllerLogger->error(__FUNCTION__ . ' - TicketStatuses - editStatus - error:\n ' . $event->getMessage());
            }
            $this->statuses = $this->Statuses->getStatusesFor($this->memberId);
            return new Response(['body' => json_encode($this->statuses)]);
        }

        $arDepartments = [];
        $arRowDepartments = $this->Bx24->getDepartmentsByIds([]);
        foreach($arRowDepartments as $department)
        {
            $arDepartments[$department['ID']] = $department;
        }

        $this->set('arDepartments', $arDepartments);

        $categories = $this->Categories->getCategoriesFor($this->memberId);
        $this->set('categories', $categories);

        $incidentCategories = $this->IncidentCategories->getCategoriesFor($this->memberId);
        $this->set('incidentCategories', $incidentCategories);
    }

    public function displayTicketCard($currentUser)
    {
        $this->disableAutoRender();

        $data = $this->request->getParsedBody();
        $this->BxControllerLogger->debug(__FUNCTION__ . ' - input data', [
            'data' => $data
        ]);

        if (isset($data['ticket'])) {

            $this->BxControllerLogger->debug(__FUNCTION__ . ' - ajax data', [
                'data' => $data
            ]);

            $arBitrixUsersIDs = [];
            if ($data['ticket']['bitrixUsers'])
            {
                foreach ($data['ticket']['bitrixUsers'] as $bitrixUser)
                {
                    $arBitrixUsersIDs[] = $bitrixUser['ID'];
                }
            }
            $arBitrixUsersIDs = json_encode($arBitrixUsersIDs);

            $oldTicket = $this->Tickets->get($data['ticket']['id']);
            $oldMark = $this->Statuses->get($oldTicket->status_id)->mark;

            // write to ticket_history table
            if(isset($data['code']))
            {
                $code = $data['code'];
                $eventType = $this->EventTypes->getEventTypeByCode($code);
                $arTicketFieldsByCode = [
                    'changeStatus' => 'status_id',
                    'changeCategory' => 'category_id',
                    'changeIncidentCategory' => 'incident_category_id',
                    'changeUsersForNotifications' => 'bitrix_users'
                ];

                if ($code === 'changeUsersForNotifications') {
                    $oldValue = implode(self::SEPARATOR, json_decode($oldTicket->{$arTicketFieldsByCode[$code]}));
                    $newValue = implode(self::SEPARATOR, json_decode($arBitrixUsersIDs));
                } else {
                    $oldValue = $oldTicket->{$arTicketFieldsByCode[$code]};
                    $newValue = $data['ticket'][$arTicketFieldsByCode[$code]];
                }

                $ticketHistory = $this->TicketHistory->create(
                    $oldTicket->id,
                    $currentUser['ID'],
                    $eventType->id,
                    $oldValue,
                    $newValue
                );
                if ($ticketHistory)
                {
                    $this->BxControllerLogger->debug(__FUNCTION__ . ' - successful entry to the TicketHistoryTable', ['data' => $ticketHistory]);
                } else {
                    $this->BxControllerLogger->debug(__FUNCTION__ . ' - error when writing to the TicketHistoryTable');
                }
            }

            $ticket = $this->Tickets->editTicket(
                (int)$data['ticket']['id'],
                (int)$data['ticket']['status_id'],
                (int)$data['ticket']['category_id'],
                $this->memberId,
                $arBitrixUsersIDs,
                (int)$data['ticket']['incident_category_id'],
            );
            if ($ticket) {
                $ticket['created'] = $ticket['created']->format(Bx24Component::DATE_TIME_FORMAT);
            }
            $status = $this->Statuses->get($data['ticket']['status_id']);
            $active = true;
            if ($status->mark == 2)
            {
                $active = false;
                $this->Bx24->setCompleteStatus($ticket['action_id'], $active);
            }

            // send event Ticket Changed Status
            $event = new Event('Ticket.statusChanged', $this, [
                'ticket' => $ticket,
                'status' => $status->name
            ]);
            $this->getEventManager()->dispatch($event);

            return new Response(['body' => json_encode([
                'ticket' => $ticket,
                'active' => $active
            ])]);
        } elseif (isset($data['fetch_messages'])) {
            $arHistoryActivities = [];
            // get history for tickets from CALL, EMAIL, MANUALLY
            $ticketId = $data['ticketId'];
            $sourceTypeId = $data['sourceTypeId'];

            if($ticketId && $sourceTypeId != Bx24Component::PROVIDER_OPEN_LINES)
            {
                $arHistoryActivities = $this->Bx24->searchActivitiesByTicketNumber($ticketId);
                $this->BxControllerLogger->debug(__FUNCTION__ . ' - fetch messages - params', [
                    'ticketId' => $ticketId,
                    'sourceTypeId' => $sourceTypeId,
                    'arHistoryActivities' => $arHistoryActivities
                ]);
                return new Response(['body' => json_encode($arHistoryActivities)]);
            }
        }

        // get additional info
        // deal name and id
        $arActivityAdditionalInfo = $this->Bx24->getAdditionalInfoForActivity($this->ticket['action_id']);
        $arBindings = $arActivityAdditionalInfo['bindings'];
        $dealName = '';
        $dealId = 0;

        foreach($arBindings as $binding)
        {
            if($binding['entityTypeId'] == $this->Bx24::OWNER_TYPE_DEAL)
            {
                $arDeal =  $this->Bx24->getDeal(intval($binding['entityId']));
                $dealName = $arDeal['TITLE'];
                $dealId = $arDeal['ID'];
                break;
            }
        }

        if($arActivityAdditionalInfo['folder']['ID'])
        {
            $files = $this->Bx24->getFilesForFolder($arActivityAdditionalInfo['folder']['ID']);
        } else {
            $files = [];
        }

        // get ticket history
        $ticketHistory = $this->TicketHistory->getHistoryByTicketID($this->ticket['id']);

        if ($ticketHistory)
        {
            $ticketHistory = $this->prepareTicketHistoryEventData($ticketHistory);
        }

        $this->set('ticketHistory', $ticketHistory);
        $this->set('from', $currentUser['TITLE'] ?? "{$currentUser['NAME']} {$currentUser['LAST_NAME']}");
        $this->set('ticket', $this->ticket);
        $this->set('PLACEMENT_OPTIONS', $this->placement);
        $this->set('onChangeResponsibleUrl', $this->getUrlOf('on_change_responsible', $this->domain));
        $this->set('dealName', $dealName);
        $this->set('dealId', $dealId);
        $this->set('storage', $arActivityAdditionalInfo['storage']);
        $this->set('folder', $arActivityAdditionalInfo['folder']);
        $this->set('files', $files);
        return $this->render('display_ticket_card');
    }

    public function sendFeedback($answer, $currentUser, array $ticketAttributes = [])
    {
        $this->disableAutoRender();

        if (is_bool($answer)) {
            $answer = [
                'from' => $currentUser['NAME'],
                'message' => "",
                "user_id" => $currentUser['ID'],
                "attach" => [],
            ];
            $this->set('needCloseApp', 0);
        } else {
            $ticketId = $this->request->getData('ticket_id') ?? $_POST['ticket_id'];
            $this->ticket = $this->Tickets->get($ticketId);
            $this->sendMessage($answer, $currentUser, $ticketAttributes);
            $this->set('needCloseApp', 1);
        }
        $this->set('answer', $answer);
        $this->set('ajax', $this->getUrlOf('crm_settings_interface', $this->domain));

        return $this->render('send_feedback');
    }

    public function handleCrmActivity()
    {
        $this->disableAutoRender();
        $this->viewBuilder()->disableAutoLayout();

        $event = $this->request->getData('event');
        $data = $this->request->getData('data');
        $idActivity = $data['FIELDS']['ID'];
        $prevActivityId = $idActivity;

        $this->BxControllerLogger->debug(__FUNCTION__ . ' - input params', [
            'event' => $event,
            'data' => $data
        ]);

        if($event == Bx24Component::CRM_NEW_ACTIVITY_EVENT)
        {
            $arActivityData = $this->Bx24->getActivityAndRelatedDataById($idActivity);
            $activity = $arActivityData['activity'];

            $this->BxControllerLogger->debug(__FUNCTION__ . ' - source activity', [
                'id' => $idActivity,
                'activity' => $activity,
                'bindings' => $arActivityData['bindings']
            ]);
            $sourceProviderType = $activity['PROVIDER_ID'];

            $sourceTypeOptions = $this->Options->getSettingsFor($this->memberId);
            if(
                !$this->Bx24->checkOptionalActivity(
                    $sourceProviderType,
                    intval($activity['DIRECTION'])
                )
            )
            {
                $this->BxControllerLogger->debug(__FUNCTION__ . ' - skip activity', [
                    'id' => $idActivity,
                    'provider' => $sourceProviderType,
                    'direction' => $activity['DIRECTION'],
                ]);
                return;
            }


            $yesCreateTicket = $this->Bx24->checkEmailActivity($event, $activity['SUBJECT'], $activity['PROVIDER_TYPE_ID'])
                && $sourceTypeOptions['sources_on_email'];
            $yesCreateTicket |= $this->Bx24->checkOCActivity($event, $sourceProviderType) && $sourceTypeOptions['sources_on_open_channel'];
            $yesCreateTicket |= $this->Bx24->checkCallActivity($event, $sourceProviderType) && $sourceTypeOptions['sources_on_phone_calls'];

            if($yesCreateTicket)
            {
                $ticketId = $this->Tickets->getNextID();
                $subject = $this->Bx24->getTicketSubject($ticketId);
                if($activityId = $this->Bx24->createTicketBy($activity, $subject))
                {
                    // ticket is activity
                    //$activity = $this->Bx24->getActivityById($activityId);
                    $activityInfo = $this->Bx24->getActivityAndRelatedDataById($activityId);
                    $activity = $activityInfo['activity'];
                    $bindings = $activityInfo['bindings'];

                    $activity['PROVIDER_TYPE_ID'] = $sourceProviderType;
                    $status = $this->Statuses->getFirstStatusForMemberTickets($this->memberId, TicketStatusesTable::MARK_STARTABLE);

                    $defaultCategory = $this->Categories->getDefaultFor($this->memberId);
                    $defaultIncidentCategory = $this->IncidentCategories->getDefaultFor($this->memberId);

                    $ticketRecord = $this->Tickets->create(
                        $this->memberId,
                        $activity,
                        $defaultCategory->id,
                        $status['id'],
                        (int)$prevActivityId,
                        $defaultIncidentCategory->id,
                        ''
                    );
                    if($bindings)
                    {
                        foreach($bindings as $binding)
                        {
                            if($binding['entityTypeId'] != $activity['OWNER_TYPE_ID'] || $binding['entityId'] != $activity['OWNER_ID'])
                            {
                                $activityId = $activity['ID'];
                                $entityId = $binding['entityId'];
                                $entityTypeId = $binding['entityTypeId'];

                                // write into ticket_bindings table
                                $activityBinding = $this->TicketBindings->create($activity['ID'], $entityId, $entityTypeId);
                                $this->BxControllerLogger->debug(__FUNCTION__ . ' - write activity binding into DB', ['activityBinding' => $activityBinding]);
                            }
                        }
                    }
                    $this->BxControllerLogger->debug(__FUNCTION__ . ' - write ticket record into DB', [
                        'prevActivityId' => $prevActivityId,
                        'errors' => $ticketRecord->getErrors(),
                        'ticketRecord' => $ticketRecord,
                        'ticketActivity' => $activity
                    ]);

                    // send event Ticket Created
                    $event = new Event('Ticket.created', $this, [
                        'ticket' => $ticketRecord,
                        'status' => $status->name,
                        'ticketAttributes' => $this->Bx24->getOneTicketAttributes($activity)
                    ]);
                    $this->getEventManager()->dispatch($event);
                }
            } else {
                $this->BxControllerLogger->debug(__FUNCTION__ . ' - activity is not match or On', [
                    'settings' => $sourceTypeOptions,
                    'event' => $event,
                    'provider' => $activity['PROVIDER_ID']
                ]);

                $matches = [];
                $isResponseOnTicket = mb_ereg($this->Bx24::TICKET_PREFIX . '(\d+)', $activity['SUBJECT'], $matches);
                if($isResponseOnTicket)
                {
                    // response on ticket
                    // send event
                    $this->BxControllerLogger->debug(__FUNCTION__ . ' - it is customer response', [
                        'idActivity' => $idActivity,
                        'memberId' => $this->memberId,
                        'matches' => $matches
                    ]);

                    $ticket = $this->Tickets->get($matches[1]);
                    $status = $this->Statuses->get($ticket['status_id']);

                    $this->BxControllerLogger->debug(__FUNCTION__ . ' - getting data', [
                        'ticket' => $ticket,
                        'status' => $status
                    ]);

                    $event = new Event('Ticket.receivingCustomerResponse', $this, [
                        'ticket' => $ticket,
                        'status' => $status->name
                    ]);
                    $this->getEventManager()->dispatch($event);
                }
            }
        }

        if($event == Bx24Component::CRM_UPDATE_ACTIVITY_EVENT)
        {
            $arActivityData = $this->Bx24->getActivityAndRelatedDataById($idActivity);
            $activity = $arActivityData['activity'];

            $this->BxControllerLogger->debug(__FUNCTION__ . ' - update activity event', [
                'id' => $idActivity,
                'activity' => $activity,
            ]);

            $ourActivityTypeAndName = $this->Bx24->getActivityTypeAndName();

            // we need set status close for ticket in db
            if($activity && $activity['PROVIDER_ID'] == 'REST_APP' && $activity['PROVIDER_TYPE_ID'] == $ourActivityTypeAndName['TYPE_ID'])
            {
                $finalStatus = $this->Statuses->getFinalStatus($this->memberId);
                $ticket = $this->Tickets->getByActivityIdAndMemberId($idActivity, $this->memberId);

                if($activity['COMPLETED'] == "Y" && $ticket->status_id != $finalStatus->id)
                {
                    if (!$ticket->resolutions)
                    {
                        $resultNoResolution = $this->Bx24->reopenActivitySendBitrixNotificationNoResolution($idActivity);
                        $this->BxControllerLogger->debug(__FUNCTION__ . ' - reopenActivitySendBitrixNotificationNoResolution', [$resultNoResolution]);
                        return;
                    }
                    $resultUpdate = $this->Tickets->editTicket($ticket->id, $finalStatus->id, null, $this->memberId);

                    $this->BxControllerLogger->debug(__FUNCTION__ . ' - result update ticket', [
                        'ticket' => $ticket,
                        'resultUpdate' => $resultUpdate
                    ]);

                    // send event Ticket Changed Status
                    $this->ticketAttributes = $this->Bx24->getOneTicketAttributes($activity);
                    $event = new Event('Ticket.statusChanged', $this, [
                        'ticket' => $resultUpdate,
                        'status' => $finalStatus->name
                    ]);
                    $this->getEventManager()->dispatch($event);
                }
            }
        }

        if($event == Bx24Component::CRM_DELETE_ACTIVITY_EVENT)
        {
            $result = $this->Tickets->deleteTicketByActionId($idActivity, $this->memberId);
            $this->BxControllerLogger->debug(__FUNCTION__ . ' - delete result', [
                'result' => $result
            ]);

            die();
        }
    }

    public function sendEmailNotification(array $arParams) : bool
    {
        // todo add from address to config
        $from = ['noreply@ourapp.com' => 'Our app'];
        switch ($arParams['type'])
        {
            case 'newTicket':
                $subject = __("Ticket ") . $arParams['subject'] . __(" has been created");
                $template = 'ticket_created';
                break;
            case 'statusChanged':
                $subject = __("Status for ticket ") . $arParams['subject'] . __(" has been changed");
                $template = 'ticket_status_changed';
                break;
            case 'responsibleChanged':
                $subject = __("Ticket ") . $arParams['subject'] . __(" has been assigned to you");
                $template = 'ticket_reassigned';
                break;
            case 'escalation':
                $subject = __("Ticket ") . $arParams['subject'] . __(" escalated");
                $template = 'ticket_escalation';
                break;
        }

        $mailer = new Mailer('default');
        $mailer
            ->setTransport('default') // email config name from app_local.php
            ->setViewVars([
                'name' => $arParams['name'],
            ])
            ->setFrom($from)
            ->setTo($arParams['email'])
            ->setEmailFormat('html')
            ->setSubject($subject)
            ->viewBuilder()
            ->setTemplate($template);
        $result = $mailer->deliver();
        // todo check result and return bool
    }

    private function saveSettings(array $data) : array
    {
        $settings = array_map(function($optionName) use ($data) {
            return [
                'member_id' => $data['member_id'],
                'opt' => $optionName,
                'value' => $data[$optionName] ?? 'off'
            ];
        }, HelpdeskOptionsTable::SOURCE_OPTIONS);
        $settings = $this->Options->updateOptions($settings);
        $this->BxControllerLogger->debug(__FUNCTION__ . ' - options', ['options' => $settings]);
        return $settings;
    }

    private function sendMessage($answer, $currentUser, array $ticketAttributes = [])
    {
        $result = false;
        $from = $answer['from'];
        $messageText = $answer['message'];
        $attachment = $this->request->getData('attachment');
        $this->BxControllerLogger->debug(__FUNCTION__ . ' - request data', [
            'from' => $from,
            'message' => $messageText,
            'attachment' => $attachment,
            'ticketAttributes' => $ticketAttributes
        ]);

        // passing email for call and manually created ticket
        $contactEmail = '';
        if($ticketAttributes['customer']['email'])
        {
            $contactEmail = $ticketAttributes['customer']['email'];
        }

        $messageObj = $this->Bx24->sendMessage($from, $messageText, $this->ticket, $attachment, $currentUser, $contactEmail);

        $this->BxControllerLogger->debug(__FUNCTION__ . ' - Bx24 - sendMessage', [
            'parameters' => [
                'from' => $from,
                'message' => $messageText,
                'ticket' => $this->ticket->toArray()
            ],
            'result' => $messageObj
        ]);

        if($messageObj)
        {
            $result = true;
        }

        return $result;
    }

    public function handleTicketStatusChange($event, $ticket, $status)
    {
        $this->BxControllerLogger->debug(__FUNCTION__ . ' - input data', [
            'ticket' => $ticket,
            'status' => $status,
            'memberId' => $this->memberId,
            'ticketAttributes' => $this->ticketAttributes
        ]);

        $arBitrixUsersIDs = [];
        if ($ticket['bitrix_users'])
        {
            foreach (json_decode($ticket['bitrix_users']) as $bitrixUser)
            {
                $arBitrixUsersIDs[] = 'user_' . $bitrixUser;
            }
        }

        // we need collect necessary data and the run bp
        $arTemplateParameters = [
            'eventType' => 'notificationChangeTicketStatus',
            'activityId' => $ticket['action_id'],
            'ticketStatus' => $status,
            'ticketNumber' => 'GS-' . $ticket['id'],
            'ticketSubject' => $this->ticketAttributes['subject'],
            'ticketResponsibleId' => 'user_' . ($this->ticketAttributes['responsible']['id'] ?? $this->ticketAttributes['responsible']),
            'answerType' => '',
            'sourceType' => $ticket['source_type_id'],
            'ticketUsers' => $arBitrixUsersIDs
        ];

        $this->BxControllerLogger->debug(__FUNCTION__ . ' - workflow parameters', [
            'arTemplateParameters' => $arTemplateParameters
        ]);

        $entityTypeId = intval($this->ticketAttributes['ENTITY_TYPE_ID']);
        $arOption = $this->Options->getOption('notificationChangeTicketStatus' . Bx24Component::MAP_ENTITIES[$entityTypeId], $this->memberId);
        $templateId = intval($arOption['value']);

        $this->BxControllerLogger->debug(__FUNCTION__ . ' - template', [
            'templateId' => $templateId
        ]);

        switch($entityTypeId)
        {
            case Bx24Component::OWNER_TYPE_CONTACT:
                $entityId = intval($this->ticketAttributes['customer']['id']);
                break;

            case Bx24Component::OWNER_TYPE_COMPANY:
                $entityId = intval($this->ticketAttributes['customer']['id']);
                break;

            default:
                $entityId = 0;
        }

        $this->BxControllerLogger->debug(__FUNCTION__ . ' - entity', [
            'entityId' => $entityId,
            'entityType' => Bx24Component::MAP_ENTITIES[$entityTypeId]
        ]);

        if($templateId && $entityId)
        {
            $arResultStartWorkflow = $this->Bx24->startWorkflowFor($templateId, $entityId, $entityTypeId, $arTemplateParameters);
            $this->BxControllerLogger->debug(__FUNCTION__ . ' - result', [
                'arResultStartWorkflow' => $arResultStartWorkflow
            ]);
        } else {
            $this->BxControllerLogger->debug(__FUNCTION__ . ' - Missing required data to start workflow');
        }
    }

    public function handleReceivingCustomerResponse($event, $ticket, $status)
    {
        $this->BxControllerLogger->debug(__FUNCTION__ . ' - input data', [
            'ticket' => $ticket,
            'status' => $status,
            'memberId' => $this->memberId,
        ]);

        $arBitrixUsersIDs = [];
        if ($ticket['bitrix_users'])
        {
            foreach (json_decode($ticket['bitrix_users']) as $bitrixUser)
            {
                $arBitrixUsersIDs[] = 'user_' . $bitrixUser;
            }
        }

        $arActivityData = $this->Bx24->getActivityAndRelatedDataById($ticket['action_id']);
        $activity = $arActivityData['activity'];
        $ticketAttributes = $this->Bx24->getOneTicketAttributes($activity);

        $this->BxControllerLogger->debug(__FUNCTION__ . ' - ticket attributes', [
            'arActivityData' => $arActivityData,
            'ticketAttributes' => $ticketAttributes
        ]);

        // we need collect necessary data and the run bp
        $arTemplateParameters = [
            'eventType' => 'notificationReceivingCustomerResponse',
            'activityId' => $ticket['action_id'],
            'ticketStatus' => $status,
            'ticketNumber' => 'GS-' . $ticket['id'],
            'ticketSubject' => $ticketAttributes['subject'],
            'ticketResponsibleId' => 'user_' . $ticketAttributes['responsible'],
            'answerType' => 'Reply',
            'sourceType' => $ticket['source_type_id'],
            'ticketUsers' => $arBitrixUsersIDs
        ];

        $this->BxControllerLogger->debug(__FUNCTION__ . ' - workflow parameters', [
            'arTemplateParameters' => $arTemplateParameters
        ]);

        $entityTypeId = intval($ticketAttributes['ENTITY_TYPE_ID']);
        $arOption = $this->Options->getOption('notificationReceivingCustomerResponse' . Bx24Component::MAP_ENTITIES[$entityTypeId], $this->memberId);
        $templateId = intval($arOption['value']);

        $this->BxControllerLogger->debug(__FUNCTION__ . ' - get template', [
            'templateId' => $templateId
        ]);

        switch($entityTypeId)
        {
            case Bx24Component::OWNER_TYPE_CONTACT:
                $entityId = intval($ticketAttributes['customer']['id']);
                break;

            case Bx24Component::OWNER_TYPE_COMPANY:
                $entityId = intval($ticketAttributes['customer']['id']);
                break;

            default:
                $entityId = 0;
        }

        $this->BxControllerLogger->debug(__FUNCTION__ . ' - entity', [
            'entityId' => $entityId,
            'entityType' => Bx24Component::MAP_ENTITIES[$entityTypeId]
        ]);

        if($templateId && $entityId)
        {
            $arResultStartWorkflow = $this->Bx24->startWorkflowFor($templateId, $entityId, $entityTypeId, $arTemplateParameters);
            $this->BxControllerLogger->debug(__FUNCTION__ . ' - start workflow result', [
                'arResultStartWorkflow' => $arResultStartWorkflow
            ]);
        } else {
            $this->BxControllerLogger->debug(__FUNCTION__ . ' - Missing required data to start workflow');
        }
    }

    public function handleResolutionAdded($event, $ticketId, $authorId, $resolutionText, $formattedTime)
    {
        $this->BxControllerLogger->debug(__FUNCTION__ . ' - input data', [
            'ticketId' => $ticketId,
            'authorId' => $authorId,
            'resolutionText' => $resolutionText,
            'formattedTime' => $formattedTime
        ]);

        $ticket = $this->Tickets->get($ticketId);
        $activities = $this->Bx24->getActivities([$ticket->action_id]);
        $ticketAttributes = $this->Bx24->getOneTicketAttributes($activities[$ticket->action_id]);

        $arBitrixUsersIDs = [];
        if ($ticket['bitrix_users'])
        {
            foreach (json_decode($ticket['bitrix_users']) as $bitrixUser)
            {
                $arBitrixUsersIDs[] = 'user_' . $bitrixUser;
            }
        }

        $this->BxControllerLogger->debug(__FUNCTION__ . ' - getted data', [
            'ticket' => $ticket,
            'activities' => $activities,
            'ticketAttributes' => $ticketAttributes
        ]);

        // we need collect necessary data and the run bp
        $arTemplateParameters = [
            'eventType' => 'notificationResolutionAdded',
            'ticketNumber' => Bx24Component::TICKET_PREFIX . $ticketId,
            'ticketSubject' => $ticketAttributes['subject'],
            'activityId' => $ticket->action_id,
            'authorId' => 'user_' . $authorId,
            'resolutionText' => $resolutionText,
            'formattedTime' => $formattedTime,
            'ticketUsers' => $arBitrixUsersIDs
        ];

        $this->BxControllerLogger->debug(__FUNCTION__ . ' - workflow parameters', [
            'arTemplateParameters' => $arTemplateParameters
        ]);

        $this->Options = $this->getTableLocator()->get('HelpdeskOptions');
        $entityTypeId = intval($ticketAttributes['ENTITY_TYPE_ID']);
        $arOption = $this->Options->getOption('notificationResolutionAdded' . Bx24Component::MAP_ENTITIES[$entityTypeId], $this->memberId);
        $templateId = intval($arOption['value']);

        $this->BxControllerLogger->debug(__FUNCTION__ . ' - template', [
            'templateId' => $templateId
        ]);

        switch($entityTypeId)
        {
            case Bx24Component::OWNER_TYPE_CONTACT:
                $entityId = intval($ticketAttributes['customer']['id']);
                break;

            case Bx24Component::OWNER_TYPE_COMPANY:
                $entityId = intval($ticketAttributes['customer']['id']);
                break;

            default:
                $entityId = 0;
        }

        $this->BxControllerLogger->debug(__FUNCTION__ . ' - entity', [
            'entityId' => $entityId,
            'entityType' => Bx24Component::MAP_ENTITIES[$entityTypeId]
        ]);

        if($templateId && $entityId)
        {
            $arResultStartWorkflow = $this->Bx24->startWorkflowFor($templateId, $entityId, $entityTypeId, $arTemplateParameters);
            $this->BxControllerLogger->debug(__FUNCTION__ . ' - result', [
                'arResultStartWorkflow' => $arResultStartWorkflow
            ]);
        } else {
            $this->BxControllerLogger->debug(__FUNCTION__ . ' - Missing required data to start workflow');
        }
    }

    public function handleTicketCreated($event, $ticket, $status, $ticketAttributes)
    {
        $this->BxControllerLogger->debug(__FUNCTION__ . ' - input data', [
            'ticket' => $ticket,
            'status' => $status,
            'memberId' => $this->memberId,
            'ticketAttributes' => $ticketAttributes
        ]);

        $arBitrixUsersIDs = [];
        if ($ticket['bitrix_users'])
        {
            foreach (json_decode($ticket['bitrix_users']) as $bitrixUser)
            {
                $arBitrixUsersIDs[] = 'user_' . $bitrixUser;
            }
        }

        // we need collect necessary data and the run bp
        $arTemplateParameters = [
            'eventType' => 'notificationCreateTicket',
            'activityId' => $ticket['action_id'],
            'ticketStatus' => $status,
            'ticketNumber' => Bx24Component::TICKET_PREFIX . $ticket['id'],
            'ticketSubject' => $ticketAttributes['subject'],
            'ticketResponsibleId' => 'user_' . $ticketAttributes['responsible'],
            'answerType' => '',
            'sourceType' => $ticket['source_type_id'],
            'ticketUsers' => $arBitrixUsersIDs
        ];

        $this->BxControllerLogger->debug(__FUNCTION__ . ' - workflow parameters', [
            'arTemplateParameters' => $arTemplateParameters
        ]);

        $this->Options = $this->getTableLocator()->get('HelpdeskOptions');
        $entityTypeId = intval($ticketAttributes['ENTITY_TYPE_ID']);
        $arOption = $this->Options->getOption('notificationCreateTicket' . Bx24Component::MAP_ENTITIES[$entityTypeId], $this->memberId);
        $templateId = intval($arOption['value']);

        $this->BxControllerLogger->debug(__FUNCTION__ . ' - template', [
            'templateId' => $templateId
        ]);

        switch($entityTypeId)
        {
            case Bx24Component::OWNER_TYPE_CONTACT:
                $entityId = intval($ticketAttributes['customer']['id']);
                break;

            case Bx24Component::OWNER_TYPE_COMPANY:
                $entityId = intval($ticketAttributes['customer']['id']);
                break;

            default:
                $entityId = 0;
        }

        $this->BxControllerLogger->debug(__FUNCTION__ . ' - entity', [
            'entityId' => $entityId,
            'entityType' => Bx24Component::MAP_ENTITIES[$entityTypeId]
        ]);

        if($templateId && $entityId)
        {
            $arResultStartWorkflow = $this->Bx24->startWorkflowFor($templateId, $entityId, $entityTypeId, $arTemplateParameters);
            $this->BxControllerLogger->debug(__FUNCTION__ . ' - result', [
                'arResultStartWorkflow' => $arResultStartWorkflow
            ]);
        } else {
            $this->BxControllerLogger->debug(__FUNCTION__ . ' - Missing required data to start workflow');
        }
    }

    private function getFileNameByUrlHeaders($url)
    {
        $fileName = '';

        $headers = get_headers($url, 1);

        $this->BxControllerLogger->debug('getFileNameByUrlHeaders', [
            'url' => $url,
            'headers' => $headers
        ]);

        if (isset($headers['Content-Disposition']))
        {
            $headerLine = $headers['Content-Disposition'];
            preg_match_all("/[^\'\']+$/", $headerLine, $matches);
            if ($matches[0][0])
            {
                $fileName = rawurldecode($matches[0][0]);
            }
        }

        return $fileName;
    }

    private function assignNewDeal($data, $currentUser)
    {
        $this->disableAutoRender();

        $this->BxControllerLogger->debug(__FUNCTION__ . ' - input data', [
            'data' => $data
        ]);

        if($data['newDealId'] && $data['oldDealId'] && $data['activityId'] > 0)
        {
            $arResult = $this->Bx24->reassignActivityOnNewDeal($data['activityId'], $data['oldDealId'], $data['newDealId']);

            $this->BxControllerLogger->debug(__FUNCTION__ . ' - reassign result', [
                'arResult' => $arResult
            ]);

            if($arResult)
            {
                $delete = $this->TicketBindings->deleteIfExists($data['activityId'], $data['oldDealId'], $this->Bx24::OWNER_TYPE_DEAL);
                $row = $this->TicketBindings->create($data['activityId'], $data['newDealId'], $this->Bx24::OWNER_TYPE_DEAL);

                $this->BxControllerLogger->debug(__FUNCTION__ . ' - tickets binding result', [
                    'delete' => $delete,
                    'new_row' => $row
                ]);

                $arDealData = [
                    'id' => $arResult['deal']['id'],
                    'title' => $arResult['deal']['title']
                ];

                return new Response(['body' => json_encode([
                    'success' => true,
                    'data' => $arDealData
                ])]);
            }
        }

        return new Response(['body' => json_encode([
            'success' => false,
            'message' => __('Bad request')
        ])]);
    }

    private function addResolution($data, $currentUser)
    {
        $this->disableAutoRender();

        $this->BxControllerLogger->debug(__FUNCTION__ . ' - input data', [
            'data' => $data
        ]);

        if($data['resolutionText'] && $data['ticketId'] && $data['ticketId'] > 0)
        {
            $this->Resolutions = $this->getTableLocator()->get('Resolutions');

            $record = $this->Resolutions->addRecord([
                'member_id' => $this->memberId,
                'author_id' => $currentUser['ID'],
                'ticket_id' => $data['ticketId'],
                'text' => $data['resolutionText']
            ]);

            if($record->hasErrors())
            {
                $errorLines = [];
                foreach($record->getErrors() as $prop => $error)
                {
                    $bugs = array_map(function($bug) use ($prop)
                        {
                            return "{$prop} - {$bug};";
                        }
                    , array_values($error));
                    $errorLines = array_merge($errorLines, $bugs);
                }

                $errorMessage = implode("\n", $errorLines);

                return new Response(['body' => json_encode([
                    'success' => false,
                    'message' => $errorMessage
                ])]);
            }

            // write to ticket_history table
            if(isset($data['code']))
            {
                $code = $data['code'];
                $eventType = $this->EventTypes->getEventTypeByCode($code);

                $ticketHistory = $this->TicketHistory->create(
                    $data['ticketId'],
                    $currentUser['ID'],
                    $eventType->id,
                    null,
                    $record->id
                );
                if ($ticketHistory)
                {
                    $this->BxControllerLogger->debug(__FUNCTION__ . ' - successful entry to the TicketHistoryTable', ['data' => $ticketHistory]);
                } else {
                    $this->BxControllerLogger->debug(__FUNCTION__ . ' - error when writing to the TicketHistoryTable');
                }
            }

            $record['fullName'] = implode(' ', [$currentUser['NAME'], $currentUser['LAST_NAME']]);
            $record['formattedTime'] = $record->created->format(Bx24Component::DATE_TIME_FORMAT);
            $record['formattedText'] = str_replace(PHP_EOL, '<br>', $record['text']);

            // send event Resolution add
            $event = new Event('Ticket.resolutionAdded', $this, [
                'ticketId' => $data['ticketId'],
                'authorId' => $currentUser['ID'],
                'resolutionText' => $data['resolutionText'],
                'formattedTime' => $record['formattedTime']
            ]);
            $this->getEventManager()->dispatch($event);

            return new Response(['body' => json_encode([
                'success' => true,
                'record' => $record
            ])]);
        }

        return new Response(['body' => json_encode([
            'success' => false,
            'message' => __('Bad request')
        ])]);
    }

    public function handleAddOrDeleteFiles($data, $currentUser)
    {
        $this->disableAutoRender();
        $data = $this->request->getParsedBody();

        $this->BxControllerLogger->debug(__FUNCTION__ . ' - input data', [
            'data' => $data
        ]);
        // write to ticket_history table
        if(isset($data['code']))
        {
            $code = $data['code'];
            $eventType = $this->EventTypes->getEventTypeByCode($code);
            $value = $data['value'];
            if($data['code'] == 'addFiles')
            {
                $value = implode('|', $data['value']);
            }

            $ticketHistory = $this->TicketHistory->create(
                $data['ticketId'],
                $currentUser['ID'],
                $eventType->id,
                null,
                $value
            );
            if ($ticketHistory)
            {
                $this->BxControllerLogger->debug(__FUNCTION__ . ' - successful entry to the TicketHistoryTable', ['data' => $ticketHistory]);
            } else {
                $this->BxControllerLogger->debug(__FUNCTION__ . ' - error when writing to the TicketHistoryTable');
            }
            return new Response(['body' => json_encode([
                'success' => true,
                'message' => 'Successful entry to the ticket_history table'
            ])]);
        }
        return new Response(['body' => json_encode([
            'success' => false,
            'message' => __('Bad request')
        ])]);
    }

    public function prepareTicketHistoryEventData($arTicketHistory)
    {
        $arUsers = [];

        foreach ($arTicketHistory as $key => $history)
        {
            // prepare time format
            $arTicketHistory[$key]->created = $history->created->i18nFormat('yyyy-MM-dd HH:mm:ss');
            // collect user ids to get additional info later
            if ($history->user_id > 0)
            {
                // changed by
                $arUsers[] = $history->user_id;
            }

            if ($history->event_type->code == 'changeResponsible')
            {
                // old/new responsible
                $arUsers[] = $history->old_value;
                $arUsers[] = $history->new_value;
            }

            if ($history->event_type->code == 'changeUsersForNotifications')
            {
                // notifications users
                $oldBitrixUsers = [];
                $newBitrixUsers = [];
                if ($history->old_value)
                {
                    $oldBitrixUsers = explode('|', $history->old_value);
                }
                if ($history->new_value)
                {
                    $newBitrixUsers = explode('|', $history->new_value);
                }

                $arUsers = array_merge($arUsers, $oldBitrixUsers, $newBitrixUsers);
            }
        }

        // get additional info on users
        $arUsers = array_unique($arUsers);
        $arUsersAttributes = $this->Bx24->getUsersAttributes($arUsers);

        foreach ($arTicketHistory as $key => $history)
        {
            // add changeby user info to history
            if ($history->user_id > 0)
            {
                $arTicketHistory[$key]->changeByInfo = $arUsersAttributes[$history->user_id];
            }

            // fill description template for history
            switch($history->event_type->code) {
                case 'changeResponsible':
                    $arOldResp = $arUsersAttributes[$history->old_value];
                    $arNewResp = $arUsersAttributes[$history->new_value];
                    if ($arOldResp['photo'])
                    {
                        $oldRespValue = '<img class="rounded-circle avatar-img-history" alt="' . $arOldResp['title'] . '" src="'. $arOldResp['photo'] .'">' . $arOldResp['title'];
                    }
                    else
                    {
                        $oldRespValue = '<span class="border rounded-circle p-0">' . $arOldResp['abr'] .'</span>' . $arOldResp['title'];
                    }

                    if ($arNewResp['photo'])
                    {
                        $newRespValue = '<img class="rounded-circle avatar-img-history" alt="' . $arNewResp['title'] . '" src="'. $arNewResp['photo'] .'">' . $arNewResp['title'];
                    }
                    else
                    {
                        $newRespValue = '<span class="border rounded-circle p-0">' . $arNewResp['abr'] .'</span>' . $arNewResp['title'];
                    }

                    $arTicketHistory[$key]->event_type->template = str_replace(['#OLD#', '#NEW#'], [$oldRespValue, $newRespValue], $history->event_type->template);
                    break;
                case 'changeStatus':
                    $oldStatus = $this->statuses[$history->old_value]['name'];
                    $newStatus = $this->statuses[$history->new_value]['name'];
                    $arTicketHistory[$key]->event_type->template = str_replace(['#OLD#', '#NEW#'], [$oldStatus, $newStatus], $history->event_type->template);
                    break;
                case 'changeCategory':
                    $oldCategory = $this->categories[$history->old_value]['name'];
                    $newCategory = $this->categories[$history->new_value]['name'];
                    $arTicketHistory[$key]->event_type->template = str_replace(['#OLD#', '#NEW#'], [$oldCategory, $newCategory], $history->event_type->template);
                    break;
                case 'changeIncidentCategory':
                    $oldIncidentCategory = $this->incidentCategories[$history->old_value]['name'];
                    $newIncidentCategory = $this->incidentCategories[$history->new_value]['name'];
                    $arTicketHistory[$key]->event_type->template = str_replace(['#OLD#', '#NEW#'], [$oldIncidentCategory, $newIncidentCategory], $history->event_type->template);
                    break;
                case 'changeUsersForNotifications':
                    $oldBitrixUsersText = '';
                    $newBitrixUsersText = '';
                    if ($history->old_value)
                    {
                        $oldBitrixUsers = explode('|', $history->old_value);
                        foreach ($oldBitrixUsers as $userID)
                        {
                            $oldBitrixUsersText .= $arUsersAttributes[$userID]['title'] . ', ';
                        }
                    }
                    else
                    {
                        $oldBitrixUsersText = ' ' . __('None');
                    }
                    if ($history->new_value)
                    {
                        $newBitrixUsers = explode('|', $history->new_value);
                        foreach ($newBitrixUsers as $userID)
                        {
                            $newBitrixUsersText .= $arUsersAttributes[$userID]['title'] . ', ';
                        }
                    }
                    else
                    {
                        $newBitrixUsersText = ' ' . __('None');
                    }
                    $newBitrixUsersText = rtrim($newBitrixUsersText, ", \t\n");
                    $oldBitrixUsersText = rtrim($oldBitrixUsersText, ", \t\n");
                    $arTicketHistory[$key]->event_type->template = str_replace(['#OLD#', '#NEW#'], [$oldBitrixUsersText, $newBitrixUsersText], $history->event_type->template);
                    break;
                case 'addFiles':
                    $newFiles = str_replace('|', ', ', $history->new_value);
                    $arTicketHistory[$key]->event_type->template = str_replace('#NEW#', $newFiles, $history->event_type->template);
                    break;
                case 'deleteFile':
                    $arTicketHistory[$key]->event_type->template = str_replace('#OLD#', $history->new_value, $history->event_type->template);
                    break;
                case 'addResolution':
                default:
                    break;
            }
        }

        return $arTicketHistory;
    }
}
