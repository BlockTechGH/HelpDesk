<?php
declare(strict_types=1);

namespace App\Controller;

use App\Controller\Component\Bx24Component;
use App\Model\Table\HelpdeskOptionsTable;
use Cake\Core\Configure;
use Cake\Event\EventInterface;
use Cake\Http\Response;
use Cake\Routing\Router;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

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

        if ($action == 'displaySettingsInterface') 
        {
            $this->options = $this->Options->getSettingsFor($this->memberId);
            $this->statuses = $this->Statuses->getStatusesFor($this->memberId);
            $this->categories = [];

            $this->set('options', $this->options);
            $this->set('statuses', $this->statuses);
            $this->set('categories', $this->categories);

            if (isset($this->placement['activity_id'])) {
                $currentUser = $this->Bx24->getCurrentUser();
                $this->ticket = $this->Tickets->getByActivityIdAndMemberId($this->placement['activity_id'], $this->memberId);
                if ($this->ticket) {
                    $this->ticket->created = $this->ticket->created->format(Bx24Component::DATE_TIME_FORMAT);
                }
                $this->set('ticket', $this->ticket);
                $ticketAttributes = $this->Bx24->getTicketAttributes($this->ticket ? $this->ticket->action_id : $this->placement['activity_id']);
                $source = $ticketAttributes && $this->ticket ? $this->Bx24->getTicketAttributes($this->ticket->source_id) : null;

                if($this->ticket->source_type_id == Bx24Component::PROVIDER_OPEN_LINES && !$source['text'])
                {
                    $firstMessage = $this->Bx24->getFirstMessageInOpenChannelChat($source);
                    $source['text'] = $firstMessage['text'];
                }

                if (isset($this->placement['answer'])) {
                    $this->set('subject', $ticketAttributes['subject']);
                    return $this->sendFeedback($answer ?? true, $currentUser);
                } elseif((isset($this->placement['activity_id']) 
                    && $this->placement['action'] == 'view_activity'))
                {
                    $this->messages = []; //$this->Bx24->getMessages($ticket);
                    if(!!$answer) {
                        $this->set('subject', $ticketAttributes['subject']);
                        return $this->sendFeedback($answer, $currentUser);
                    }
                    if(!!$activity_id)
                    {
                        $set = $this->request->getData('set', false);
                        $this->Bx24->setCompleteStatus($activity_id, !!$set);
                        return new Response(['body' => json_encode(['status' => 'OK'])]);
                    }
                    $this->set('ticketAttributes', $ticketAttributes);
                    $this->set('source', $source);
                    return $this->displayTicketCard($currentUser);
                }
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
            $this->set('options', $this->options);
        } elseif(isset($data['category'])) {
            $category = $this->Categories->editCategory(
                $data['category']['id'] ?? 0, 
                $data['category']['name'], 
                $this->memberId,
                (bool)$data['category']['active']
            );
            return new Response(['body' => json_encode($category)]);
        } elseif(isset($data['ticket_status'])) {
            $mark = (int)$data['ticket_status']['mark'];
            if ($mark > 0) {
                $this->Statuses->flushMarks($mark);
            }
            $this->Statuses->editStatus(
                $data['ticket_status']['id'], 
                $data['ticket_status']['name'], 
                $this->memberId,
                (bool)$data['ticket_status']['active'],
                $mark
            );
            $this->statuses = $this->Statuses->getStatusesFor($this->memberId);
            return new Response(['body' => json_encode($this->statuses)]);
        }           
    }

    public function displayTicketCard($currentUser)
    {
        $this->disableAutoRender();

        $data = $this->request->getParsedBody();
        
        if (isset($data['ticket'])) {
            $oldTicket = $this->Tickets->get($data['ticket']['id']);
            $oldMark = $this->Statuses->get($oldTicket->status_id)->mark;
            $ticket = $this->Tickets->editTicket(
                (int)$data['ticket']['id'],
                (int)$data['ticket']['status_id'],
                (int)$data['ticket']['category_id'],
                $this->memberId
            );
            $status = $this->Statuses->get($data['ticket']['status_id']);
            if ($status->mark != $oldMark)
            {
                $active = $status->mark != 2;
                $this->Bx24->setCompleteStatus($ticket['action_id'], $active);
            }
            return new Response(['body' => json_encode([
                'ticket' => $ticket,
                'active' => $active
            ])]);
        } elseif (isset($data['fetch_messages'])) {
            return new Response(['body' => json_encode([])]);
        }
 
        $this->set('messages', $this->messages);
        $this->set('from', $currentUser['TITLE'] ?? "{$currentUser['NAME']} {$currentUser['LAST_NAME']}"); 
        $this->set('ticket', $this->ticket);
        $this->set('PLACEMENT_OPTIONS', $this->placement);
        return $this->render('display_ticket_card');
    }

    public function sendFeedback($answer, $currentUser)
    {
        $this->disableAutoRender();

        if (is_bool($answer)) {
            $answer = [
                'from' => $currentUser['NAME'],
                'message' => "",
                "user_id" => $currentUser['ID'],
                "attach" => [],
            ];
        } else {
            $ticketId = $this->request->getData('ticket_id') ?? $_POST['ticket_id'];
            $this->ticket = $this->Tickets->get($ticketId);
            $this->sendMessage($answer, $currentUser);
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
        $activity = $this->Bx24->getActivity($idActivity);
        $this->BxControllerLogger->debug(__FUNCTION__ . ' - source activity', [
            'id' => $idActivity,
            'object' => $activity
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
            $ticketId = $this->Tickets->getLatestID() + 1;
            $subject = $this->Bx24->getTicketSubject($ticketId);
            if($activityId = $this->Bx24->createTicketBy($activity, $subject))
            {
                // ticket is activity
                $activity = $this->Bx24->getActivity($activityId);
                $activity['PROVIDER_TYPE_ID'] = $sourceProviderType;
                $status = $this->Statuses->getStartStatusForMemberTickets($this->memberId);
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
            }
        } else {
            $this->BxControllerLogger->debug(__FUNCTION__ . ' - activity is not match or On', [
                'settings' => $sourceTypeOptions,
                'event' => $event,
                'provider' => $activity['PROVIDER_ID']
            ]);
        }
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

    private function sendMessage($answer, $currentUser)
    {
        $from = $answer['from'];
        $messageText = $answer['message'];
        $attachment = $this->request->getData('attachment');
        $this->BxControllerLogger->debug(__FUNCTION__ . ' - request data', [
            'from' => $from,
            'message' => $messageText,
            'attachment' => $attachment,
        ]);

        $messageObj = $this->Bx24->sendMessage($from, $messageText, $this->ticket, $attachment, $currentUser);
        //$messages = $this->Bx24->getMessages($ticket);
        $this->BxControllerLogger->debug(__FUNCTION__ . ' - Bx24 - sendMessage', [
            'parameters' => [
                'from' => $from,
                'message' => $messageText,
                'ticket' => $this->ticket->toArray()
            ],
            'result' => $messageObj
        ]);
    }
}