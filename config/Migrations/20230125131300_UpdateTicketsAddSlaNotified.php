<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class UpdateTicketsAddSlaNotified extends AbstractMigration
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
        $table = $this->table('tickets');
        $table->addColumn('sla_notified', 'boolean', [
            'default' => 0,
            'after' => 'source_id'
        ]);
        $table->save();
    }

    public function down()
    {
        $table = $this->table('tickets');
        $table->removeColumn('sla_notified');
        $table->save();
    }
}
