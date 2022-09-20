<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateTicketStatuses extends AbstractMigration
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
        $table->addColumn('name', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);
        $table->addColumn('member_id', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);
        $table->addColumn('active', 'string', [
            'default' => 1,
            'limit' => 1,
            'null' => false,
        ]);
        $table->create();
    }

    public function down()
    {
        $this->table('ticket_statuses')->drop()->save();
    }
}
