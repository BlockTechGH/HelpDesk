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

use Cake\Event\EventInterface;
use Cake\Core\Configure;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\Error\Debugger;
use Cake\View\Exception\MissingTemplateException;

class InstallationsController extends AppController
{
    /**
     * beforeFilter callback.
     *
     * @param \Cake\Event\EventInterface $event Event.
     * @return \Cake\Http\Response|null|void
     */
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
    }

    public function installApp()
    {
        $this->viewBuilder()->disableAutoLayout();

        $data = $this->request->getParsedBody();

        $this->loadComponent('Bx24');

        // 1. save/updatre bitrix tokens in models
        $this->BitrixTokens = $this->getTableLocator()->get('BitrixTokens');

        // 2.0 Install/reinstall connector
        $this->Bx24->installConnector();

        // 2.1 Get installed data
        $arInstalledData = $this->Bx24->getInstalledData();

        // 2.2 Register connector - https://dev.1c-bitrix.ru/rest_help/imconnector/methods/imconnector_register.php
        // 2.3 Bind on OnImConnectorMessageAdd, OnImConnectorLineDelete, OnImConnectorStatusDelete - https://dev.1c-bitrix.ru/rest_help/imconnector/events/index.php
        // 2.4 Add application activity type - https://dev.1c-bitrix.ru/rest_help/crm/rest_activity/crm_activity_type_add.php
        // 2.5 Placement in CRM cards

        die();
    }
}
