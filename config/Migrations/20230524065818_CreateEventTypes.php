<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateEventTypes extends AbstractMigration
{
    public function up()
    {
        $table = $this->table('event_types');
        $table->addColumn('name', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);
        $table->addColumn('template', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
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
        $this->table('event_types')->drop()->save();
    }
}
