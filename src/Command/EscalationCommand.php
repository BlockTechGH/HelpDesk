<?php
namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Core\Configure;
use Cake\I18n\FrozenTime;
use Cake\Controller\ComponentRegistry;
use App\Controller\Component\Bx24Component;
use App\Controller\CommandController;

class EscalationCommand extends Command
{
    private $memberId;
    private $packageLength = 50;
    private $level = 'initial';

    public static function getDescription(): string
    {
        return 'Command for the first level of escalation';
    }

    public function initialize(): void
    {
        $this->memberId = Configure::read('AppConfig.member_id');
        if(!$this->memberId)
        {
            $io = new ConsoleIo;
            $io->error('member_id is null, check app_config');
            $this->abort();
        }
        $this->TicketsTable = $this->fetchTable('Tickets');
        $this->TicketStatusesTable = $this->fetchTable('TicketStatuses');
        $this->HelpdeskOptionsTable = $this->fetchTable('HelpdeskOptions');

        $registry = new ComponentRegistry(new CommandController());
        $this->Bx24 = new Bx24Component($registry);
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $this->slaOptions = $this->getSlaOptionsValue();
        if(!$this->slaOptions)
        {
            $io->error('SLA settings not received, please check member_id');
            $this->abort();
        }

        $minLevelResponseTime = $this->getMinLevelResponseTime($this->slaOptions);
        $deadlineTime = $this->getDeadlineTime($minLevelResponseTime);

        $statuses = $this->TicketStatusesTable->getStatusesByMemberIdAndMarks($this->memberId, [3, 2]);
        $statusIds = array_column($statuses, 'id');

        $levelTickets = $this->TicketsTable->getTicketsExcludingStatusesAndExceedingDeadlineTime($deadlineTime, $statusIds, $this->memberId);
        $packages = $this->getTicketPackages($levelTickets);

        foreach($packages as $package)
        {
            $activitiesIds = array_column($package, 'action_id');
            $activities = $this->Bx24->getActivitiesFromCommand($activitiesIds);

            if($activities)
            {
                $responsibleIds = array_unique(array_map(function($activity) {
                    return $activity['RESPONSIBLE_ID'];
                }, array_values($activities)));
                $responsible = $this->Bx24->getUserById($responsibleIds);

                foreach($responsible as $id => $user)
                {
                    $responsible[$user['ID']] = $user;
                    unset($responsible[$id]);
                }
                sleep(1);
            }

            // get expired tickets
            $expiredTickets = $this->getExpiredTicketsInPackage($package, $activities, $responsible);
            $expiredTicketIds = array_column($expiredTickets, 'id');
            $escatatedStatus = $this->TicketStatusesTable->getEscalatedStatus($this->memberId);

            // change ticket status in database
            $result = $this->TicketsTable->changeTicketStatus($expiredTicketIds, $escatatedStatus->id);

            // run business process when changing ticket status 1 batch

            // run workflow template for notifications 1 batch
            sleep(1);
        }
        // $io->out(print_r($packages, true));

        return static::CODE_SUCCESS;
    }

    protected function getSlaOptionsValue(): array
    {
        $result = [];
        $option = 'sla_settings';
        $query = $this->HelpdeskOptionsTable->getOption($option, $this->memberId);
        if($query && isset($query->value))
        {
            $result = unserialize($query->value);
        }
        return $result;
    }

    protected function getMinLevelResponseTime($departments): int
    {
        $level = $this->level;
        $minTime = array_values($departments)[0]["{$level}RTKPI"];
        foreach($departments as $department)
        {
            $minTime = $department["{$level}RTKPI"] < $minTime ? $department["{$level}RTKPI"] : $minTime;
        }
        return $minTime;
    }

    protected function getDeadlineTime($responseTime)
    {
        $now = FrozenTime::now();
        return $now->modify("- {$responseTime} minutes");
    }

    protected function getTicketPackages($tickets): array
    {
        $length = $this->packageLength;
        $packages = array_chunk($tickets, $length);
        return $packages;
    }

    protected function getExpiredTicketsInPackage($package, $activities, $responsible): array
    {
        $expiredTickets = [];

        foreach($package as $ticket)
        {
            $responsibleId = $activities[$ticket->action_id]['RESPONSIBLE_ID'];
            $responsibleDepartments = $responsible[$responsibleId]['UF_DEPARTMENT'];
            if(count($responsibleDepartments) > 1)
            {
                $responsibleDepartmentId = $responsibleDepartments[0];
                $responseTime = $this->slaOptions[$responsibleDepartmentId]["{$this->level}RTKPI"];
                foreach($responsibleDepartments as $departmentId)
                {
                    if($this->slaOptions[$departmentId] < $responseTime)
                    {
                        $responseTime = $responsibleDepartments[$departmentId]["{$this->level}RTKPI"];
                        $responsibleDepartmentId = $departmentId;
                    }
                }
            }
            else
            {
                $responsibleDepartmentId = $responsibleDepartments[0];
                $responseTime = $this->slaOptions[$responsibleDepartmentId]["{$this->level}RTKPI"];
            }

            $deadlineTime = $this->getDeadlineTime($responseTime);
            if($ticket->modified < $deadlineTime)
            {
                $ticket->entityId = $activities[$ticket->action_id]['OWNER_ID'];
                $ticket->entityTypeId = $activities[$ticket->action_id]['OWNER_TYPE_ID'];
                $entityType = strtolower($this->Bx24::MAP_ENTITIES[$ticket->entityTypeId]);
                $ticket->workfowTempalteId = $this->slaOptions[$responsibleDepartmentId]["{$entityType}Template"];

                $expiredTickets[] = $ticket;
            }
        }

        return $expiredTickets;
    }
}