<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class UpdateBitrixTokensRemoveIndexes extends AbstractMigration
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
        $table->removeIndexByName('BY_AUTH_ID')
            ->removeIndexByName('BY_REFRESH_ID')
            ->removeIndexByName('BY_AUTH_EXPIRES');
        $table->update();
    }

    public function down()
    {
        $table = $this->table('bitrix_tokens');
        $table->addIndex([
            'auth_id',

        ], [
            'name' => 'BY_AUTH_ID',
            'unique' => false,
        ]);
        $table->addIndex([
            'refresh_id',

        ], [
            'name' => 'BY_REFRESH_ID',
            'unique' => false,
        ]);
        $table->addIndex([
            'auth_expires',

        ], [
            'name' => 'BY_AUTH_EXPIRES',
            'unique' => false,
        ]);
        $table->update();
    }
}
