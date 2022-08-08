<?php
namespace App\Controller\Component;

use Monolog\Logger;
use Cake\I18n\Time;
use Cake\Routing\Router;
use Cake\Core\Configure;
use Cake\Controller\Component;
use Monolog\Handler\StreamHandler;
use Bitrix24\Exceptions\Bitrix24ApiException;
use Bitrix24\Exceptions\Bitrix24SecurityException;

class Bx24Component extends Component {

    private $controller = null;
    private $obBx24App = null;
    private $bx24Logger = null;

    public function initialize(array $config = []): void
    {
        parent::initialize($config);
        $this->controller = $this->_registry->getController();

        $logFile = Configure::read('AppConfig.LogsFilePath') . DS . 'bx24app.log';
        $bx24Logger = new Logger('BX24');
        $bx24Logger->pushHandler(new StreamHandler($logFile, Logger::DEBUG));

        $this->obBx24App = new \Bitrix24\Bitrix24(false, $bx24Logger);

        $this->obBx24App->setApplicationScope(["crm"]);
        $this->obBx24App->setDomain($this->controller->domain);
        $this->obBx24App->setMemberId($this->controller->memberId);
        $this->obBx24App->setAccessToken($this->controller->authId);
        $this->obBx24App->setRefreshToken($this->controller->refreshId);
    }

    public function installConnector()
    {
        $postfix = Configure::read('itemsPostfix');

        $arParams = [
            'ID' => 'BT_WHATSAPP' . (($postfix) ? $postfix : ''),
            'NAME' => __('BT Whatsapp') . (($postfix) ? $postfix : ''),
            'ICON' => [
                'DATA_IMAGE' => 'data:image/svg+xml,',
                'COLOR' => '#a6ffa3',
                'SIZE' => '100%',
                'POSITION' => 'center',
            ]
        ];
    }

    public function getInstalledData()
    {
        $arData = [
            'user' => [],
            'placementList' => [],
            'activityTypeList'
        ];

        $this->obBx24App->addBatchCall('user.current', [], function($result) use (&$arData)
        {
            if($result['result'])
            {
                $arData['user'] = $result['result'];
            }
        });

        $this->B24App->processBatchCalls();

        return $arData;
    }
}
