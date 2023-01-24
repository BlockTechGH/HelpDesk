<?php
namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Core\Configure;
use Cake\I18n\FrozenTime;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Cake\Controller\ComponentRegistry;
use App\Controller\Component\Bx24Component;
use App\Controller\CommandController;

class EscalationInitialLevelCommand extends Command
{
    private $memberId;
    private $packageLength = 50;
    private $level = 'initial';
    private $logger;

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

        // logger
        $logFile = Configure::read('AppConfig.LogsFilePath') . DS . 'escalation_first_level.log';
        $this->logger = new Logger('EscalationFirstLevel');
        $this->logger->pushHandler(new StreamHandler($logFile, Logger::DEBUG));
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $this->logger->debug("*** start ***");

        $this->slaOptions = $this->getSlaOptionsValue();
        if(!$this->slaOptions)
        {
            $io->error('SLA settings not received, please check member_id');
            $this->abort();
        }

        $minLevelResponseTime = $this->getMinLevelResponseTime($this->slaOptions);
        $deadlineTime = $this->getDeadlineTime($minLevelResponseTime);

        $statuses = $this->TicketStatusesTable->getStatusesByMemberIdAndMarks($this->memberId, [$this->TicketStatusesTable::MARK_FINAL, $this->TicketStatusesTable::MARK_ESCALATED]);
        $statusIds = array_column($statuses, 'id');

        $levelTickets = $this->TicketsTable->getTicketsExcludingStatusesAndExceedingDeadlineTime($deadlineTime, $statusIds, $this->memberId);
        $packages = $this->getTicketPackages($levelTickets);

        foreach($packages as $i => $package)
        {
            $this->logger->debug(__FUNCTION__ . " - package: " . $i, ['package' => $package]);

            $activitiesIds = array_column($package, 'action_id');
            $activities = $this->Bx24->getActivitiesFromCommand($activitiesIds);

            $this->logger->debug(__FUNCTION__ . " - activities: " . $i, ['activities' => $activities]);

            if($activities)
            {
                $responsibleIds = array_unique(array_map(function($activity) {
                    return $activity['RESPONSIBLE_ID'];
                }, array_values($activities)));
                $arRowResponsible = $this->Bx24->getUserById($responsibleIds);

                $responsible = [];
                foreach($arRowResponsible as $id => $user)
                {
                    $responsible[$user['ID']] = $user;
                    unset($arRowResponsible[$id]);
                }

                $this->logger->debug(__FUNCTION__ . " - responsibles: " . $i, [
                    'responsible' => $responsible
                ]);

                sleep(1);
            }

            // get expired tickets
            $expiredTickets = $this->getExpiredTicketsInPackage($package, $activities, $responsible, $io);

            $this->logger->debug(__FUNCTION__ . " - expiredTickets: " . $i, [
                'expiredTickets' => $expiredTickets
            ]);

            $expiredTicketIds = array_column($expiredTickets, 'id');
            $escatatedStatus = $this->TicketStatusesTable->getEscalatedStatus($this->memberId);

            // change ticket status in database
            $resultChangeTicketStatus = $this->TicketsTable->changeTicketsStatus($expiredTicketIds, $escatatedStatus->id);

            $this->logger->debug(__FUNCTION__ . " - result change ticket status: " . $i, [
                'result' => $resultChangeTicketStatus
            ]);

            // run workflow when changing ticket status
            $resultToChangeStatus = $this->Bx24->startWorkflowsToChangeStatuses($expiredTickets, $activities, $escatatedStatus);

            $this->logger->debug(__FUNCTION__ . " - result start change status workflows: " . $i, [
                'result' => $resultToChangeStatus
            ]);

            // run workflow for sla notifications
            $resultSlaNotification = $this->Bx24->startWorkflowsToExpiredTickets($expiredTickets, $this->level, $activities);

            $this->logger->debug(__FUNCTION__ . " - result sla notification workflows: " . $i, [
                'result' => $resultSlaNotification
            ]);

            sleep(1);
        }

        $this->logger->debug("*** end ***");

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

    protected function getExpiredTicketsInPackage($package, $activities, $responsible, $io): array
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