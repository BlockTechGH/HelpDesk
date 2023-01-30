<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * TicketBindingsFixture
 */
class TicketBindingsFixture extends TestFixture
{
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
                'activity_id' => 1,
                'entity_id' => 1,
                'entity_type_id' => 1,
                'created' => '2023-01-30 09:20:21',
                'modified' => '2023-01-30 09:20:21',
            ],
        ];
        parent::init();
    }
}
