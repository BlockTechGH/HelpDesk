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
            $options = $this->saveSettings($data);
        } elseif(isset($data['optName'])) {
            $category = $this->Categories->addCategory($data['optName'], $this->memberId);
            return new Response(['body' => json_encode($category)]);
        } elseif(isset($data['categories'])) {
            $categories = $this->Categories->updateCategories($data['categories'], $this->memberId);
            return new Response(['body' => json_encode($categories)]);
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