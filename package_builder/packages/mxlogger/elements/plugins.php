<?php

return [
    'mxLoggerRotate' => [
        'description' => 'Ротация (автоудаление) старых записей лога mxLogger по mxlogger.log_lifetime.',
        'content' => 'file:elements/plugins/plugin.mxloggerrotate.php',
        'events' => [
            'OnMODXInit',
        ],
    ],
];
