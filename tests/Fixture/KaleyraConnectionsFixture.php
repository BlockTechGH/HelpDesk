<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * KaleyraConnectionsFixture
 */
class KaleyraConnectionsFixture extends TestFixture
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
                'member_id' => 'Lorem ipsum dolor sit amet',
                'phone_number' => 'Lorem ipsum dolor sit amet',
                'sid' => 'Lorem ipsum dolor sit amet',
                'created' => '2022-08-05 08:47:26',
                'modified' => '2022-08-05 08:47:26',
            ],
        ];
        parent::init();
    }
}
