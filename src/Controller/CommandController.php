<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Core\Configure;
use Cake\I18n\FrozenTime;

class CommandController extends AppController
{
    private $level;
    private $slaOptions;
    private $packageLength;

    public function initialize(array $config = []): void
    {
        parent::initialize($config);

        $this->memberId = Configure::read('AppConfig.member_id');
        $bxToken = $this->fetchTable('BitrixTokens')->getTokenObjectByMemberId($this->memberId);
        $this->domain = $bxToken->domain;
        $this->authId = $bxToken->auth_id;
        $this->refreshId = $bxToken->refresh_id;

        $this->loadComponent('Bx24');
        $this->HelpdeskOptionsTable = $this->fetchTable('HelpdeskOptions');

        if(isset($config['level']) && $config['level'])
        {
            $this->level = ($config['level'] === 'subsequent') ? 'subsequent' : 'initial';
        }

        if(isset($config['slaOptions']) && $config['slaOptions'])
        {
            $this->slaOptions = $config['slaOptions'];
        }

        if(isset($config['packageLength']) && $config['packageLength'])
        {
            $this->packageLength = $config['packageLength'];
        }
    }

    public function getMinLevelResponseTime(): int
    {
        $level = $this->level;
        $departments = $this->slaOptions;

        $minTime = array_values($departments)[0]["{$level}RTKPI"];

        foreach($departments as $department)
        {
            $minTime = $department["{$level}RTKPI"] < $minTime ? $department["{$level}RTKPI"] : $minTime;
        }
        return intval($minTime);
    }

    public function getDeadlineTime($responseTime): FrozenTime
    {
        $now = FrozenTime::now();
        return $now->modify("- {$responseTime} minutes");
    }

    public function getTicketPackages($tickets): array
    {
        $length = $this->packageLength;
        $packages = array_chunk($tickets, $length);
        return $packages;
    }

    public function getExpiredTicketsInPackage($package, $activities, $responsible): array
    {
        $expiredTickets = [];

        foreach($package as $ticket)
        {
            $responsibleId = $activities[$ticket->action_id]['RESPONSIBLE_ID'];
            $responsibleDepartments = $responsible[$responsibleId]['UF_DEPARTMENT'];

            $responseTime = 0;

            // get min response time
            foreach($responsibleDepartments as $departmentId)
            {
                if(isset($this->slaOptions[$departmentId]) && $responseTime > 0 && $this->slaOptions[$departmentId]["{$this->level}RTKPI"] < $responseTime)
                {
                    $responseTime = $this->slaOptions[$departmentId]["{$this->level}RTKPI"];
                    $responsibleDepartmentId = $departmentId;
                }

                if(isset($this->slaOptions[$departmentId]) && $responseTime === 0 && $this->slaOptions[$departmentId]["{$this->level}RTKPI"] > 0)
                {
                    $responseTime = $this->slaOptions[$departmentId]["{$this->level}RTKPI"];
                    $responsibleDepartmentId = $departmentId;
                }
            }

            if($responseTime && $ticket->created < $this->getDeadlineTime($responseTime))
            {
                $ticket->ticketAttributes = $this->Bx24->getOneTicketAttributes($activities[$ticket->action_id]);
                $ticket->entityId = $activities[$ticket->action_id]['OWNER_ID'];
                $ticket->entityTypeId = $activities[$ticket->action_id]['OWNER_TYPE_ID'];
                $entityType = strtolower($this->Bx24::MAP_ENTITIES[$ticket->ticketAttributes['ENTITY_TYPE_ID']]);

                if($ticket->ticketAttributes['ENTITY_TYPE_ID'] == $this->Bx24::OWNER_TYPE_DEAL)
                {
                    $ticket->slaNotificationTemplateId = 0;
                    $ticket->responsibleUsers = [];
                    $ticket->responsibleId = $responsibleId;
                    $ticket->responsibleDepartmentId = $responsibleDepartmentId;
                    $ticket->changeStatusTemplateId = 0;
                } else {
                    $ticket->slaNotificationTemplateId = $this->slaOptions[$responsibleDepartmentId]["{$entityType}Template"];
                    $ticket->responsibleUsers = $this->slaOptions[$responsibleDepartmentId]["{$this->level}NotificationUsers"];
                    $ticket->responsibleId = $responsibleId;
                    $ticket->responsibleDepartmentId = $responsibleDepartmentId;
                    $ticket->changeStatusTemplateId = $this->HelpdeskOptionsTable->getOption('notificationChangeTicketStatus' . ucfirst($entityType), $this->memberId)->value;
                }

                $expiredTickets[] = $ticket;
            }
        }

        return $expiredTickets;
    }
}