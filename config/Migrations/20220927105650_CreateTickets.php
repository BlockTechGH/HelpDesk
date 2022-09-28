<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateTickets extends AbstractMigration
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
        $table->addColumn('status_id', 'integer', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('category_id', 'integer', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('member_id', 'string', [
            'default' => null,
            'null' => false,
            'limit' => 255
        ]);
        $table->addColumn('action_id', 'integer', [
            'default' => 1,
            'null' => false,
        ]);
        $table->addColumn('source_type_id', 'integer', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('source_id', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false
        ]);
        $table->addColumn('created', 'datetime', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('modified', 'datetime', [
            'default' => null,
            'null' => false,
        ]);
        $table->addIndex(['status_id', 'category_id']);
        $table->addForeignKey('status_id', 'ticket_statuses', 'id');
        $table->addForeignKey('category_id', 'ticket_categories', 'id');
        $table->create();
    }

    public function down()
    {
        $this->table('tickets')->drop()->save();
    }
}
