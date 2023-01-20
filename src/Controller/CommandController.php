<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Core\Configure;

class CommandController extends AppController
{
    public function initialize(): void
    {
        $this->memberId = Configure::read('AppConfig.member_id');
        $bxToken = $this->fetchTable('BitrixTokens')->getTokenObjectByMemberId($this->memberId);
        $this->domain = $bxToken->domain;
        $this->authId = $bxToken->auth_id;
        $this->refreshId = $bxToken->refresh_id;
    }
}