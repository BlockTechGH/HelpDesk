<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * BitrixTokensFixture
 */
class BitrixTokensFixture extends TestFixture
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
                'domain' => 'Lorem ipsum dolor sit amet',
                'auth_id' => 'Lorem ipsum dolor sit amet',
                'refresh_id' => 'Lorem ipsum dolor sit amet',
                'auth_expires' => 1,
                'created' => '2022-09-13 06:04:08',
                'modified' => '2022-09-13 06:04:08',
            ],
        ];
        parent::init();
    }
}
