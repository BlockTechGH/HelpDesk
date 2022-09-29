<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * TicketsFixture
 */
class TicketsFixture extends TestFixture
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
                'status_id' => 1,
                'category_id' => 1,
                'member_id' => 'Lorem ipsum dolor sit amet',
                'action_id' => 1,
                'source_type_id' => 1,
                'source_id' => 'Lorem ipsum dolor sit amet',
            ],
        ];
        parent::init();
    }
}
