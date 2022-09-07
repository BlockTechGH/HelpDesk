<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateLanguages extends AbstractMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     * @return void
     */
    public function up()
    {
        $table = $this->table('lang_codes');
        $table->addColumn('name', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);
        $table->addColumn('code', 'string', [
           'default' => null,
           'limit' => 100,
           'null' => false
        ]);
        $table->create();
    }

    public function down()
    {
        $this->table('lang_codes')->drop()->save();
    }
}
