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

        $controllerName = $this->request->getParam('controller');

        if($controllerName == 'Installations')
        {
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
            }
        }
    }
}
