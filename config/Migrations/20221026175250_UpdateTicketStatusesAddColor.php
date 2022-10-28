<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class UpdateTicketStatusesAddColor extends AbstractMigration
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
        $table->addColumn('color', 'string', [
            'null' => false,
            'default' => 0,
            'limit' => 6,
        ]);
        $table->save();
    }

    public function down()
    {
        $table = $this->table('ticket_statuses');
        $table->removeColumn('color');
        $table->save();
    }
}
