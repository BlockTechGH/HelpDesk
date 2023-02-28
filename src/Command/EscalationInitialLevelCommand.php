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
        return 'Command for the Initial level of escalation';
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
        $this->slaOptions = $this->getSlaOptionsValue();

        $config = [
            'level' => $this->level,
            'slaOptions' => $this->slaOptions,
            'packageLength' => $this->packageLength
        ];
        $this->commandController = new CommandController();
        $this->commandController->initialize($config);

        $registry = new ComponentRegistry($this->commandController);
        $this->Bx24 = new Bx24Component($registry);

        // logger
        $logFile = Configure::read('AppConfig.LogsFilePath') . DS . 'escalation_initial_level.log';
        $this->logger = new Logger('EscalationInitialLevel');
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

        $minLevelResponseTime = $this->commandController->getMinLevelResponseTime();
        $deadlineTime = $this->commandController->getDeadlineTime($minLevelResponseTime);

        $statuses = $this->TicketStatusesTable->getStatusesByMemberIdAndMarks($this->memberId, [$this->TicketStatusesTable::MARK_FINAL, $this->TicketStatusesTable::MARK_ESCALATED]);
        $statusIds = array_column($statuses, 'id');

        $levelTickets = $this->TicketsTable->getTicketsExcludingStatusesAndExceedingDeadlineTime($deadlineTime, $statusIds, $this->memberId);
        $packages = $this->commandController->getTicketPackages($levelTickets);

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
            $expiredTickets = $this->commandController->getExpiredTicketsInPackage($package, $activities, $responsible);

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

            // mark tikcet as violated
            foreach($expiredTickets as $ticket)
            {
                $resultViolation = $this->TicketsTable->markAsViolated($ticket);

                if($resultViolation['error'])
                {
                    $this->logger->error(__FUNCTION__ . " - result violation ticket: " . $i, [
                        'id' => $ticket->id,
                        'result' => $resultViolation
                    ]);
                } else {
                    $this->logger->debug(__FUNCTION__ . " - result violation ticket: " . $i, [
                        'id' => $ticket->id,
                        'result' => $resultViolation
                    ]);
                }
            }

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
}