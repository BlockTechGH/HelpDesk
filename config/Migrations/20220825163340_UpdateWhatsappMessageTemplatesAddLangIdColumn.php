<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class UpdateWhatsappMessageTemplatesAddLangIdColumn extends AbstractMigration
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
        $table->removeColumn('lang_code');
        $table->addColumn('id_lang', 'integer', [
           'default' => null,
           'null' => false,
        ]);
        $table->update();
    }

    public function down()
    {
        $table = $this->table('whatsapp_message_templates');
        $table->removeColumn('id_lang');
        $table->addColumn('lang_code', 'string', [
            'default' => null,
            'null' => false,
            'limit' => 255,
        ]);
        $table->update();
    }
}
