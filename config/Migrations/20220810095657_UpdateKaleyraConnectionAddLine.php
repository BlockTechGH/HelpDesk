<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class UpdateKaleyraConnectionAddLine extends AbstractMigration
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
        $table->addColumn('line', 'integer', [
            'default' => null,
            'null' => false,
            'after' => 'sid',
            'limit' => 255
        ]);
        $table->update();
    }

    public function down()
    {
        $this->table('kaleyra_connections')->removeColumn('line')->update();
    }
}
