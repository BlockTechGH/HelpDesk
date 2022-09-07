<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class UpdateBitrixTokensAddDomain extends AbstractMigration
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
        $table->addColumn('domain', 'string', [
            'default' => null,
            'null' => false,
            'after' => 'member_id',
            'limit' => 255
        ]);
        $table->update();
    }

    public function down()
    {
        $this->table('bitrix_tokens')->removeColumn('domain')->update();
    }
}
