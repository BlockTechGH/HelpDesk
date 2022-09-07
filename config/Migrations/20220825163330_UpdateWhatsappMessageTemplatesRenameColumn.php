<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class UpdateWhatsappMessageTemplatesRenameColumn extends AbstractMigration
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
        $table->renameColumn('handler', 'header');
        $table->update();
    }

    public function down()
    {
        $table = $this->table('whatsapp_message_templates');
        $table->renameColumn('header', 'handler');
        $table->update();
    }
}
