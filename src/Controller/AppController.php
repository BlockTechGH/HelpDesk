<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     0.2.9
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 */
namespace App\Controller;

use Cake\Core\Configure;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Cake\Error\Debugger;
use Cake\Controller\Controller;

/**
 * Application Controller
 *
 * Add your application-wide methods in the class below, your controllers
 * will inherit them.
 *
 * @link https://book.cakephp.org/4/en/controllers.html#the-app-controller
 */
class AppController extends Controller
{
    public $isAccessFromBitrix = false;
    public $memberId = null;
    public $authId = null;
    public $refreshId = null;
    public $authExpires = null;
    public $domain = null;


    public $isCallFromKaleyra = false;
    public $wanumber = null;
    public $from = null;
    public $message = null;
    public $mobile = null;
    public $status = null;
    public $messageId = null;

    /**
     * Initialization hook method.
     *
     * Use this method to add common initialization code like loading components.
     *
     * e.g. `$this->loadComponent('FormProtection');`
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('RequestHandler');
        $this->loadComponent('Flash');

        $logFile = Configure::read('AppConfig.LogsFilePath') . DS . 'app.log';
        $this->AppControllerLogger = new Logger('AppController');
        $this->AppControllerLogger->pushHandler(new StreamHandler($logFile, Logger::DEBUG));

        $controllerName = $this->request->getParam('controller');

        $this->AppControllerLogger->debug('Start request', [
            'controllerName' => $controllerName,
            'data' => $this->request->getParsedBody(),
            'query' => $this->request->getQueryParams()
        ]);

        $auth = $this->request->getData('auth');
        if($controllerName == 'Installations')
        {
            $event = $this->request->getData('event');

            if($event && $auth)
            {
                $this->isAccessFromBitrix = true;
                $this->memberId = $auth['member_id'];
                $this->authId = $auth['access_token'];
                $this->refreshId = $auth['refresh_token'];
                $this->authExpires = $auth['expires_in'];
                $this->domain = $auth['domain'];
            }
        }

        if($controllerName == 'Bitrix')
        {
            $this->memberId = $this->request->getData('member_id') ?? $auth['member_id'];
            $this->authId = $this->request->getData('AUTH_ID') ?? $auth['auth_id'] ?? $auth['access_token'];
            $this->refreshId = $this->request->getData('REFRESH_ID');
            if(!$this->refreshId)
            {
                $this->refreshId = $this->getTableLocator()
                    ->get('BitrixTokens')
                    ->getTokenObjectByMemberId($this->memberId)
                    ->refresh_id;
            }
            $this->authExpires = $this->request->getData('AUTH_EXPIRES') ?? $auth['expires_in'];
            $this->domain = $this->request->getQuery('DOMAIN') ?? $auth['domain'];

            if($this->memberId && $this->authId && $this->refreshId && $this->authExpires && $this->domain)
            {
                $this->isAccessFromBitrix = true;
            }
        }

        if($controllerName == 'Kaleyra')
        {
            $this->wanumber = $this->request->getQuery('wanumber');
            $this->from = $this->request->getQuery('from');
            $this->messageId = $this->request->getQuery('id');
            $this->message = json_decode($this->request->getQuery('message'), true);
            $this->mobile = $this->request->getQuery('mobile');
            $this->status = $this->request->getQuery('status');

            if($this->wanumber && $this->from && $this->mobile)
            {
                $this->isCallFromKaleyra = true;
            }

            if($this->mobile && $this->status)
            {
                $this->isCallFromKaleyra = true;
            }
        }
    }
}
