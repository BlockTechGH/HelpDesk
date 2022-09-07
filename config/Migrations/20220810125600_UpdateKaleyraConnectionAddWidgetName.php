<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class UpdateKaleyraConnectionAddWidgetName extends AbstractMigration
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
        $table = $this->table('kaleyra_connections');
        $table->addColumn('widget_name', 'string', [
            'default' => null,
            'null' => false,
            'after' => 'phone_number',
            'limit' => 255
        ]);
        $table->update();
    }

    public function down()
    {
        $this->table('kaleyra_connections')->removeColumn('widget_name')->update();
    }
}
