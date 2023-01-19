<?php
namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Core\Configure;
use Cake\I18n\FrozenTime;

class EscalationCommand extends Command
{
    protected $defaultTable = 'Tickets';
    private $memberId;
    private $packageLength = 50;

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
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $slaOptions = $this->getSlaOptionsValueForMemberId($this->memberId);
        if(!$slaOptions)
        {
            $io->error('SLA settings not received, please check member_id');
            $this->abort();
        }
        $minFirstLevelResponseTime = $this->getMinFirstLevelResponseTime($slaOptions);
        $firstLevelTickets = $this->getTicketsExceedingResponseTime($this->memberId, $minFirstLevelResponseTime);
        $packages = $this->getTicketPackages($firstLevelTickets);

        $io->out(print_r($packages, true));

        return static::CODE_SUCCESS;
    }

    protected function getSlaOptionsValueForMemberId($memberId): array
    {
        $result = [];
        $option = 'sla_settings';
        $query = $this->fetchTable('HelpdeskOptions')->getOption($option, $memberId);
        if($query && isset($query->value))
        {
            $result = unserialize($this->fetchTable('HelpdeskOptions')->getOption($option, $memberId)->value);
        }
        return $result;
    }

    private function getMinFirstLevelResponseTime($slaOptions): int
    {
        $minTime = array_values($slaOptions)[0]['initialRTKPI'];
        foreach($slaOptions as $department)
        {
            $minTime = $department['initialRTKPI'] < $minTime ? $department['initialRTKPI'] : $minTime;
        }
        return $minTime;
    }

    protected function getTicketsExceedingResponseTime($memberId, $responseTime)
    {
        $now = FrozenTime::now();
        $responseTime = $now->modify("- {$responseTime} minutes");
        $startStatus = $this->fetchTable('TicketStatuses')->getFirstStatusForMemberTickets($memberId);
        return $this->fetchTable()->find()->where(['member_id' => $memberId, 'status_id' => $startStatus->id, 'modified <' => $responseTime])->toArray();
    }

    protected function getTicketPackages($tickets)
    {
        $length = $this->packageLength;
        $packages = array_chunk($tickets, $length);
        return $packages;
    }
}