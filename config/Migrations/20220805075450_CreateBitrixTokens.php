<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateBitrixTokens extends AbstractMigration
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
        $table = $this->table('bitrix_tokens');
        $table->addColumn('member_id', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);
        $table->addColumn('domain', 'string', [
            'default' => null,
            'null' => false,
            'limit' => 255
        ]);
        $table->addColumn('auth_id', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);
        $table->addColumn('refresh_id', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);
        $table->addColumn('auth_expires', 'integer', [
            'default' => null,
            'limit' => 7,
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
        $table->create();
    }

    public function down()
    {
        $this->table('bitrix_tokens')->drop()->save();
    }
}
