<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreatekaleyraConnections extends AbstractMigration
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
        $table = $this->table('kaleyra_connections');
        $table->addColumn('member_id', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);
        $table->addColumn('phone_number', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);
        $table->addColumn('sid', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);
        $table->addColumn('created', 'datetime', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('modified', 'datetime', [
            'default' => null,
            'null' => false,
        ]);
        $table->addIndex([
            'member_id',

            ], [
            'name' => 'BY_MEMBER_ID',
            'unique' => false,
        ]);
        $table->addIndex([
            'phone_number',

            ], [
            'name' => 'BY_PHONE_NUMBER',
            'unique' => false,
        ]);
        $table->addIndex([
            'sid',

            ], [
            'name' => 'BY_SID',
            'unique' => false,
        ]);
        $table->create();
    }

    public function down()
    {
        $this->table('kaleyra_connections')->drop()->save();
    }
}
