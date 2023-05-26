<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * EventTypesFixture
 */
class EventTypesFixture extends TestFixture
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
                'template' => 'Lorem ipsum dolor sit amet',
                'created' => '2023-05-24 10:00:06',
                'modified' => '2023-05-24 10:00:06',
            ],
        ];
        parent::init();
    }
}
