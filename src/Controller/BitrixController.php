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

    public function initialize() : void
    {
        parent::initialize();
        $event = $this->request->getData('event');
        $auth = $this->request->getData('auth');

        if($event && $auth)
        {
            $this->isAccessFromBitrix = true;
            $this->memberId = $auth['member_id'];
            $this->authId = $auth['access_token'];
            $this->refreshId = $auth['refresh_token'] ?? "";
            $this->authExpires = $auth['expires_in'];
            $this->domain = $auth['domain'];
        } else {
            $this->authId = $this->request->getData('AUTH_ID');
            $this->refreshId = $this->request->getData('REFRESH_ID') ?? '';
            $this->authExpires = (int)($this->request->getData('AUTH_EXPIRES'));
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

        if (!$this->refreshId) {
            $this->BitrixTokens = $this->getTableLocator()->get('BitrixTokens');
            $tokenRecord = $this->BitrixTokens->getTokenObjectByMemberId($this->memberId);
            $this->refreshId = $tokenRecord['refresh_id'];
        }
        $this->loadComponent('Bx24');
        $this->Options = $this->getTableLocator()->get('HelpdeskOptions');
        $this->Statuses = $this->getTableLocator()->get('TicketStatuses');
        $this->Categories = $this->getTableLocator()->get('TicketCategories');
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
                $this->memberId
            );
            return new Response(['body' => json_encode($category)]);
        } elseif(isset($data['categories'])) {
            $categories = $this->Categories->updateCategories($data['categories'], $this->memberId);
            return new Response(['body' => json_encode($categories)]);
        } elseif(isset($data['ticket_status'])) {
            $status = $this->Statuses->editStatus(
                $data['ticket_status']['id'], 
                $data['ticket_status']['name'], 
                $this->memberId,
            );
            return new Response(['body' => json_encode($status)]);
        } elseif(isset($data['statuses'])) {
            $statuses = $this->Statuses->updateStatuses($data['statuses'], $this->memberId);
            return new Response(['body' => json_encode($statuses)]);
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
        $this->BxControllerLogger->debug(__FUNCTION__ . ' - request', $data);
        $idActivity = $data['FIELDS']['ID'];
        $activity = $this->Bx24->getActivity($idActivity);
        $activityType = $activity['type'];

        $sourceTypeOptions = $this->Options->getSettingsFor($this->memberId);
        $this->BxControllerLogger->debug(__FUNCTION__ . ' - settings', [
            'memberId' => $this->memberId,
            'settings' => $sourceTypeOptions
        ]);

        if($event == 'ONCRMACTIVITYADD')
        {
            if($activityType['NAME'] == 'E-mail' && $sourceTypeOptions['sources_on_email'])
            {
                $this->BxControllerLogger->debug("Create a ticket by e-mail");
                $this->Bx24->createTicketBy($activity);
            } elseif($activityType['NAME'] == 'User action' && $sourceTypeOptions['sources_on_open_channel'])
            {
                $this->BxControllerLogger->debug("Create a ticket by Open Channel chat");
                $this->Bx24->createTicketBy($activity);
            } elseif($activityType['NAME'] == 'Call' && $sourceTypeOptions['sources_on_phone_calls'])
            {
                $this->BxControllerLogger->debug("Create a ticket by phone call");
                $this->Bx24->createTicketBy($activity);
            } else {
                $this->BxControllerLogger->debug(__FUNCTION__ . ' - OnCrmActivityAdd - not match', [
                    'activityType' => $activityType,
                ]);
            }
        } else {
            echo "None of";
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