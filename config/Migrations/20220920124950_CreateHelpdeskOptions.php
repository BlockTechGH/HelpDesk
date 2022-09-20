<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateHelpdeskOptions extends AbstractMigration
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
        $table = $this->table('helpdesk_options');
        $table->addColumn('member_id', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);
        $table->addColumn('key', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false
        ]);
        $table->addColumn('value', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false
        ]);
        $table->create();
    }

    public function down()
    {
        $this->table('helpdesk_options')->drop()->save();
    }
}
