<?php

return [
    'AppConfig' => [
        // see real values in project wiki
        'client_id' => '',
        'client_secret' => '',

        // portal id
        'member_id' => '',

        // logs configurations
        'LogsFilePath' => '/var/log/helpdesk',
        'LogsLifeTime' => 10,

        // fill for local development, leave empty for production
        'appBaseUrl' => '', // ngrok url
        'itemsPostfix' => '', // unique postfix for app item names

        'timeZone' => ''
    ],
];

?>
