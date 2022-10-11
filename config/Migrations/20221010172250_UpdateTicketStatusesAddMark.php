<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class UpdateTicketStatusesAddMark extends AbstractMigration
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
        $table = $this->table('ticket_statuses');
        $table->addColumn('mark', 'integer', [
            'null' => false,
            'default' => 0,
        ]);
        $table->save();
    }

    public function down()
    {
        $table = $this->table('ticket_statuses');
        $table->removeColumn('mark');
        $table->save();
    }
}
