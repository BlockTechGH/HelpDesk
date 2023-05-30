<?php
declare(strict_types=1);

use Migrations\AbstractSeed;

/**
 * EventTypes seed.
 */
class EventTypesSeed extends AbstractSeed
{
    public function run()
    {
        $data = [
            [
                'name' => 'Change of responsible',
                'template' => 'Responsible person has been changed from #OLD# to #NEW#.',
                'code' => 'changeResponsible',
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s')
            ],
            [
                'name' => 'Change of status',
                'template' => 'Ticket status has been changed from #OLD# to #NEW#.',
                'code' => 'changeStatus',
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s')
            ],
            [
                'name' => 'Category change',
                'template' => 'Ticket category has been changed from #OLD# to #NEW#.',
                'code' => 'changeCategory',
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s')
            ],
            [
                'name' => 'Change incident category',
                'template' => 'Ticket incident category has been changed from #OLD# to #NEW#.',
                'code' => 'changeIncidentCategory',
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s')
            ],
            [
                'name' => 'Changing users for notifications',
                'template' => 'The users for receiving notifications has changed. Old list: #OLD#. New list: #NEW#.',
                'code' => 'changeUsersForNotifications',
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s')
            ],
            [
                'name' => 'Adding resolution',
                'template' => 'Resolution has been added.',
                'code' => 'addResolution',
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s')
            ],
            [
                'name' => 'Adding files',
                'template' => 'Files #NEW# has been added to the ticket.',
                'code' => 'addFiles',
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s')
            ],
            [
                'name' => 'Deleting file',
                'template' => 'File #OLD# has been deleted from the ticket.',
                'code' => 'deleteFile',
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s')
            ]
        ];

        $table = $this->table('event_types');
        $table->insert($data)->save();
    }
}
