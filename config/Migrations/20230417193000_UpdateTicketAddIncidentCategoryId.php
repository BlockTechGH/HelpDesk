<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class UpdateTicketAddIncidentCategoryId extends AbstractMigration
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
        $table->addColumn('incident_category_id', 'integer', [
            'default' => null,
            'null' => true,
            'after' => 'category_id'
        ]);
        $table->save();
    }

    public function down()
    {
        $table = $this->table('tickets');
        $table->removeColumn('incident_category_id');
        $table->save();
    }
}
