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
                'member_id' => 'Lorem ipsum dolor sit amet, aliquet feugiat. Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget sollicitudin venenatis cum nullam, vivamus ut a sed, mollitia lectus. Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus duis vestibulum nunc mattis convallis.',
                'auth_id' => 'Lorem ipsum dolor sit amet',
                'refresh_id' => 'Lorem ipsum dolor sit amet',
                'auth_expires' => 1,
                'created' => '2022-08-05 08:47:26',
                'modified' => '2022-08-05 08:47:26',
            ],
        ];
        parent::init();
    }
}
