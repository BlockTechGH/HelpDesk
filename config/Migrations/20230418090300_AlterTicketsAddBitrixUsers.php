<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AlterTicketsAddBitrixUsers extends AbstractMigration
{
    public function up()
    {
        $table = $this->table('tickets');
        $table->addColumn('bitrix_users', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
            'after' => 'violated'
        ]);
        $table->update();
    }

    public function down()
    {
        $table = $this->table('tickets');
        $table->removeColumn('bitrix_users');
        $table->save();
    }
}
