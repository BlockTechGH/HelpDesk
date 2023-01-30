<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateTicketBindings extends AbstractMigration
{
    public function up()
    {
        $table = $this->table('ticket_bindings');
        $table->addColumn('activity_id', 'integer', [
            'default' => null,
            'null' => false,
            'limit' => 11
        ]);
        $table->addColumn('entity_id', 'integer', [
            'default' => null,
            'null' => false,
            'limit' => 11
        ]);
        $table->addColumn('entity_type_id', 'integer', [
            'default' => null,
            'null' => false,
            'limit' => 2
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
        $this->table('ticket_bindings')->drop()->save();
    }
}
