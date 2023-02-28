<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class UpdateTicketsAddViolationFields extends AbstractMigration
{
    public function up()
    {
        $table = $this->table('tickets');
        $table->addColumn('is_violated', 'boolean', [
            'default' => 0,
            'after' => 'sla_notified'
        ]);
        $table->addColumn('violated_by', 'integer', [
            'default' => null,
            'null' => true,
            'limit' => 11,
            'after' => 'is_violated'
        ]);
        $table->addColumn('violated', 'datetime', [
            'default' => null,
            'null' => true,
            'after' => 'violated_by'
        ]);
        $table->save();
    }

    public function down()
    {
        $table = $this->table('tickets');
        $table->removeColumn('is_violated');
        $table->removeColumn('violated_by');
        $table->removeColumn('violated');
        $table->save();
    }
}
