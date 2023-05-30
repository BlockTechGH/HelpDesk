<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class UpdateEventTypesAddCode extends AbstractMigration
{
    public function up()
    {
        $table = $this->table('event_types');
        $table->addColumn('code', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);
        $table->save();
    }

    public function down()
    {
        $table = $this->table('event_types');
        $table->removeColumn('code');
        $table->save();
    }
}
