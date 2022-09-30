<?php
declare(strict_types=1);

namespace App\Controller;

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

        if ($action == 'displaySettingsInterface') 
        {
            $this->options = $this->Options->getSettingsFor($this->memberId);
            $this->statuses = $this->Statuses->getStatusesFor($this->memberId);
            $this->categories = [];

            $this->set('options', $this->options);
            $this->set('statuses', $this->statuses);
            $this->set('categories', $this->categories);

            if (isset($this->placement['activity_id']) 
                && $this->placement['action'] == 'view_activity')
            {
                $this->ticket = $this->Tickets->getByActivityIdAndMemberId($this->placement['activity_id'], $this->memberId);
                $this->messages = []; //$this->Bx24->getMessages($ticket);
                return $this->displayTicketCard();
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
            $status = $this->Statuses->editStatus(
                $data['ticket_status']['id'], 
                $data['ticket_status']['name'], 
                $this->memberId,
                (bool)$data['ticket_status']['active']
            );
            return new Response(['body' => json_encode($status)]);
        }           
    }

    public function displayTicketCard()
    {
        $this->BxControllerLogger->debug('displayTicketCard');
        $this->disableAutoRender();

        $data = $this->request->getParsedBody();
        $currentUser = $this->Bx24->getCurrentUser();
        
        if (isset($data['answer'])) {
            $this->messages = $this->sendMessage();
        } elseif (isset($data['ticket'])) {
            $ticket = $this->Tickets->editTicket(
                (int)$data['ticket']['id'],
                (int)$data['ticket']['status_id'],
                (int)$data['ticket']['category_id'],
                $this->memberId
            );
            return new Response(['body' => json_encode($ticket)]);
        }

        $this->set('messages', $this->messages);
        $this->set('from', $currentUser['TITLE'] ?? "{$currentUser['NAME']} {$currentUser['LAST_NAME']}"); 
        $this->set('ticket', $this->ticket);
        $this->set('PLACEMENT_OPTIONS', $this->placement);
        return $this->render('display_ticket_card');
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
                    null, 
                    $status['id'],
                    (int)$prevActivityId
                );
                $this->BxControllerLogger->debug(__FUNCTION__ . ' - write ticket record into DB', [
                    'prevActivityId' => $prevActivityId,
                    'errors' => $ticketRecord->getErrors(),
                    'ticketRecord' => $ticketRecord,
                ]);
            } else {
                $this->BxControllerLogger->debug(__FUNCTION__ . ' - activity is not created');
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

    private function sendMessage()
    {
        $answer = $this->request->getData('answer');
        $from = $answer['from'];
        $messageText = $answer['message'];
        //$attachment = $this->request->getData('attachment');
        $ticketRecord = $this->request->getData('ticket');
        $ticket = $this->Tickets->get($ticketRecord['id']);

        $this->Bx24->sendMessage($from, $messageText, $ticket, null);
        $messages = $this->Bx24->getMessages($ticket);
        
        return $messages;
    }
}