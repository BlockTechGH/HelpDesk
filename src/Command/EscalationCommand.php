<?php
namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;

class EscalationCommand extends Command
{
    protected $defaultTable = 'Tickets';

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $statuses = $this->fetchTable('TicketStatuses')->getStatuses();
        $io->out(print_r($statuses, true));

        return static::CODE_SUCCESS;
    }
}