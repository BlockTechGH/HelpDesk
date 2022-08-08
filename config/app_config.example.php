<?php

return [
    'AppConfig' => [
        // see real values in project wiki
        'client_id' => '',
        'client_secret' => '',

        // portal id
        'member_id' => '',

        // logs configurations
        'LogsFilePath' => '/var/log/kaleyra',
        'LogsLifeTime' => 10,

        // for local development
        'portalBaseUrl' => '', // ngrok url
        'postfix' => '', // unique postfix for app item names
    ],
];

?>