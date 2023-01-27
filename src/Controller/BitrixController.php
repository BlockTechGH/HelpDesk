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
        $this->Tickets = $this->getTableLocator()->get('Tickets');
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

        // subscribe on events
        $this->BxControllerLogger->debug(__FUNCTION__ . ' - subscribed', [
            'Ticket.statusChanged', 'Ticket.receivingCustomerResponse', 'Ticket.created'
        ]);
        $eventManager = $this->getEventManager();
        $eventManager->on('Ticket.statusChanged', [$this, 'handleTicketStatusChange']);
        $eventManager->on('Ticket.receivingCustomerResponse', [$this, 'handleReceivingCustomerResponse']);
        $eventManager->on('Ticket.created', [$this, 'handleTicketCreated']);

        if ($action == 'displaySettingsInterface')
        {
            $this->options = $this->Options->getSettingsFor($this->memberId);
            $this->statuses = $this->Statuses->getStatusesFor($this->memberId);
            $this->categories = [];


            $this->set('options', $this->options);
            $this->set('statuses', $this->statuses);
            $this->set('categories', $this->categories);
            $this->set('tickets', []);

            if (isset($this->placement['activity_id'])) {
                // activity card
                $currentUser = $this->Bx24->getCurrentUser();
                $this->ticket = $this->Tickets->getByActivityIdAndMemberId($this->placement['activity_id'], $this->memberId);
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
                        $ticketAttributes['responsible'] = $this->Bx24->getUsersAttributes([$uid])[$uid];
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

                        $ticket = $this->Tickets->editTicket($ticket['id'], $status->id, null, $this->memberId);
                        return new Response(['body' => json_encode(['status' => $ticket['status_id']])]);
                    }
                    $this->BxControllerLogger->debug(__FUNCTION__ . ' - customer', $ticketAttributes['customer']);
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

            $oldTicket = $this->Tickets->get($data['ticket']['id']);
            $oldMark = $this->Statuses->get($oldTicket->status_id)->mark;
            $ticket = $this->Tickets->editTicket(
                (int)$data['ticket']['id'],
                (int)$data['ticket']['status_id'],
                (int)$data['ticket']['category_id'],
                $this->memberId
            );
            $status = $this->Statuses->get($data['ticket']['status_id']);
            $active = false;
            if ($status->mark != $oldMark)
            {
                $active = $status->mark != 2;
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

        $this->set('from', $currentUser['TITLE'] ?? "{$currentUser['NAME']} {$currentUser['LAST_NAME']}");
        $this->set('ticket', $this->ticket);
        $this->set('PLACEMENT_OPTIONS', $this->placement);
        $this->set('onChangeResponsibleUrl', $this->getUrlOf('on_change_responsible', $this->domain));
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

                    $activity['PROVIDER_TYPE_ID'] = $sourceProviderType;
                    $status = $this->Statuses->getFirstStatusForMemberTickets($this->memberId, TicketStatusesTable::MARK_STARTABLE);
                    $ticketRecord = $this->Tickets->create(
                        $this->memberId,
                        $activity,
                        1,
                        $status['id'],
                        (int)$prevActivityId
                    );
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

        // we need collect necessary data and the run bp
        $arTemplateParameters = [
            'eventType' => 'notificationChangeTicketStatus',
            'ticketStatus' => $status,
            'ticketNumber' => 'GS-' . $ticket['id'],
            'ticketSubject' => $this->ticketAttributes['subject'],
            'ticketResponsibleId' => 'user_' . ($this->ticketAttributes['responsible']['id'] ?? $this->ticketAttributes['responsible']),
            'answerType' => '',
            'sourceType' => $ticket['source_type_id']
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
            'ticketStatus' => $status,
            'ticketNumber' => 'GS-' . $ticket['id'],
            'ticketSubject' => $ticketAttributes['subject'],
            'ticketResponsibleId' => 'user_' . $ticketAttributes['responsible'],
            'answerType' => 'Reply',
            'sourceType' => $ticket['source_type_id']
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

    public function handleTicketCreated($event, $ticket, $status, $ticketAttributes)
    {
        $this->BxControllerLogger->debug(__FUNCTION__ . ' - input data', [
            'ticket' => $ticket,
            'status' => $status,
            'memberId' => $this->memberId,
            'ticketAttributes' => $ticketAttributes
        ]);

        // we need collect necessary data and the run bp
        $arTemplateParameters = [
            'eventType' => 'notificationCreateTicket',
            'ticketStatus' => $status,
            'ticketNumber' => Bx24Component::TICKET_PREFIX . $ticket['id'],
            'ticketSubject' => $ticketAttributes['subject'],
            'ticketResponsibleId' => 'user_' . $ticketAttributes['responsible'],
            'answerType' => '',
            'sourceType' => $ticket['source_type_id']
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
}
