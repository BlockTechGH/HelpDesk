<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class UpdateHelpdeskOptionsTableRenameKeyColumn extends AbstractMigration
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
        $table->renameColumn('key', 'opt');
        $table->save();
    }

    public function down()
    {
        $table = $this->table('helpdesk_options');
        $table->renameColumn('opt', 'key');
        $table->save();
    }
}
