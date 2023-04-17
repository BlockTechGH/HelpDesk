<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * IncidentCategoriesFixture
 */
class IncidentCategoriesFixture extends TestFixture
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
                'name' => 'Lorem ipsum dolor sit amet',
                'member_id' => 'Lorem ipsum dolor sit amet',
                'active' => 'L',
                'created' => '2023-04-17 10:28:43',
                'modified' => '2023-04-17 10:28:43',
            ],
        ];
        parent::init();
    }
}
