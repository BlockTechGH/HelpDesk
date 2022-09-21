<?php
declare(strict_types=1);

namespace App\Controller;

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
            $this->refreshId = $auth['refresh_token'];
            $this->authExpires = $auth['expires_in'];
            $this->domain = $auth['domain'];
        } else {
            $this->authId = $this->request->getData('AUTH_ID');
            $this->refreshId = $this->request->getData('REFRESH_ID');
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
            $none_options = ['AUTH_ID', 'REFRESH_ID', 'AUTH_EXPIRES', 'member_id', 'saveSettings'];
            $optionNames = array_diff(array_keys($data), $none_options);
            $settings = array_map(function($optionName) use ($data) { 
                return [
                    'member_id' => $data['member_id'],
                    'opt' => $optionName,
                    'value' => $data[$optionName]
                ];
            }, $optionNames);
            $this->Options->updateOptions($settings);
            $this->BxControllerLogger->debug(__FUNCTION__ - ' - options update', $optionNames); 
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
}