<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class UpdateKaleyraConnectionRemoveIndexes extends AbstractMigration
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
        $table->removeIndexByName('BY_MEMBER_ID')
            ->removeIndexByName('BY_PHONE_NUMBER')
            ->removeIndexByName('BY_SID');
        $table->update();
    }

    public function down()
    {
        $table = $this->table('kaleyra_connections');
        $table->addIndex([
            'member_id',

        ], [
            'name' => 'BY_MEMBER_ID',
            'unique' => false,
        ]);
        $table->addIndex([
            'phone_number',

        ], [
            'name' => 'BY_PHONE_NUMBER',
            'unique' => false,
        ]);
        $table->addIndex([
            'sid',

        ], [
            'name' => 'BY_SID',
            'unique' => false,
        ]);
        $table->update();
    }
}
