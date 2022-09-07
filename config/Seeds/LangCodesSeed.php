<?php
declare(strict_types=1);

use Migrations\AbstractSeed;

/**
 * LangCodes seed.
 */
class LangCodesSeed extends AbstractSeed
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeds is available here:
     * https://book.cakephp.org/phinx/0/en/seeding.html
     *
     * @return void
     */
    public function run()
    {
        $data = [
            [
                'name'=> 'English',
                'code' => 'en'
            ],
            [
                'name' => 'Arabic',
                'code' => 'ar',
            ],
        ];

        $table = $this->table('lang_codes');
        $table->insert($data)->save();
    }
}
