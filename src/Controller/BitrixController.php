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
    }

    public function displaySettingsInterface()
    {
        $data = $this->request->getParsedBody();
        $options = $this->Options->getSettingsFor($this->memberId);
        $statuses = $this->Statuses->getStatusesFor($this->memberId);
        $categories = $this->Categories->getCategoriesFor($this->memberId);
        $this->BxControllerLogger->debug(__FUNCTION__ . ' - options - ' . count($options) . ' found');

        $flashOptions = [
            'params' => [
                'dismissible' => true,
            ]
        ];

        if(isset($data['saveSettings']))
        {
            $options = $this->saveSettings($data);
        } elseif(isset($data['category'])) {
            $category = $this->Categories->editCategory(
                $data['category']['id'] ?? 0, 
                $data['category']['name'], 
                $this->memberId,
                $data['category']['active']
            );
            return new Response(['body' => json_encode($category)]);
        } elseif(isset($data['ticket_status'])) {
            $status = $this->Statuses->editStatus(
                $data['ticket_status']['id'], 
                $data['ticket_status']['name'], 
                $this->memberId,
                $data['ticket_status']['active']
            );
            return new Response(['body' => json_encode($status)]);
        }

        $this->set('domain', $this->domain);
        $this->set('options', $options);
        $this->set('statuses', $statuses);
        $this->set('categories', $categories);
        // hidden fields from app installation
        $this->set('authId', $this->authId);
        $this->set('authExpires', $this->authExpires);
        $this->set('refreshId', $this->refreshId);
        $this->set('memberId', $this->memberId);
    }

    public function handleCrmActivity()
    {
        $this->disableAutoRender();
        $this->viewBuilder()->disableAutoLayout();

        $event = $this->request->getData('event');
        $data = $this->request->getData('data');
        $idActivity = $data['FIELDS']['ID'];
        $activity = $this->Bx24->getActivity($idActivity);
        $activityType = $activity['type'];

        $sourceTypeOptions = $this->Options->getSettingsFor($this->memberId);
        if(!$this->Bx24->checkOptionalActivity($activity['PROVIDER_ID'], intval($activity['DIRECTION'])))
        {
            $this->BxControllerLogger->debug(__FUNCTION__ . ' - skip activity', [
                'id' => $idActivity,
                'provider' => $activity['PROVIDER_ID'],
                'direction' => $activity['DIRECTION'],
            ]);
            return;
        }

        $ticketBy = false;
        $isEmail = $this->Bx24->checkEmailActivity($event, $activityType['NAME']);
        if ($isEmail) {
            $ticketBy = mb_ereg('#-(\d+)', $activity['SUBJECT'], $matches) !== false;
        }
        $yesCreateTicket = $isEmail && !$ticketBy && $sourceTypeOptions['sources_on_email'];
        $yesCreateTicket |= $this->Bx24->checkOCActivity($event, $activityType['NAME'], $activity['PROVIDER_TYPE_ID']) && $sourceTypeOptions['sources_on_open_channel'];
        $yesCreateTicket |= $this->Bx24->checkCallActivity($event, $activityType['NAME']) && $sourceTypeOptions['sources_on_phone_calls'];

        if($yesCreateTicket)
        {
            $ticketId = $this->Tickets->getLatestID() + 1;
            $subject = "#{$ticketId}";                
            if($activityId = $this->Bx24->createTicketBy($activity, $subject))
            {
                // ticket is activity
                $activity = $this->Bx24->getActivity($activityId);
                // Source of ticket
                $activity['type'] = $activityType;

                $category = $this->Categories->getStartCategoryForMemberTickets($this->memberId);
                $status = $this->Statuses->getStartStatusForMemberTickets($this->memberId);
                $ticketRecord = $this->Tickets->create(
                    $this->memberId, 
                    $activity, 
                    $category['id'], 
                    $status['id']
                );
                $this->BxControllerLogger->debug(__FUNCTION__ . ' - write ticket record into DB', [
                    'status' => $status,
                    'category' => $category,
                    'ticketActivity' => $activity,
                    'ticketRecord' => $ticketRecord,
                ]);
            } else {
                $this->BxControllerLogger->debug(__FUNCTION__ . ' - activity is created');
            }
        } else {
            $this->BxControllerLogger->debug(__FUNCTION__ . ' - activity is not match or On', [
                'settings' => $sourceTypeOptions,
                'event' => $event,
                'activity' => $activityType['NAME'],
                'provider' => $activity['PROVIDER_TYPE_ID']
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
}