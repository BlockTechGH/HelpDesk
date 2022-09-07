<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateWhatsAppMessageTemplates extends AbstractMigration
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
        $table = $this->table('whatsapp_message_templates');
        $table->addColumn('name', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);
        $table->addColumn('placeholders', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);
        $table->addColumn('handler', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);
        $table->addColumn('lang_code', 'string', [
            'default' => null,
            'null' => false,
            'limit' => 255
        ]);
        $table->create();
    }

    public function down()
    {
        $this->table('whatsapp_message_templates')->drop()->save();
    }
}
