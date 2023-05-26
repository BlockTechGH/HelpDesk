<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * TicketHistoryFixture
 */
class TicketHistoryFixture extends TestFixture
{
    /**
     * Table name
     *
     * @var string
     */
    public $table = 'ticket_history';
    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [
            [
                'id' => 1,
                'ticket_id' => 1,
                'user_id' => 1,
                'event_type_id' => 1,
                'old_value' => 'Lorem ipsum dolor sit amet',
                'new_value' => 'Lorem ipsum dolor sit amet',
                'created' => '2023-05-24 09:56:40',
            ],
        ];
        parent::init();
    }
}
