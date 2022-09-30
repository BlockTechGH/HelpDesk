<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class UpdateTicketsCategoryId extends AbstractMigration
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
        $table->changeColumn('category_id', 'integer', [
            'null' => true,
            'default' => null
        ]);
        $table->save();
    }

    public function down()
    {
        $table = $this->table('tickets');
        $table->changeColumn('category_id', 'integer', [
            'null' => false,
            'default' => null,
        ]);
        $table->save();
    }
}
