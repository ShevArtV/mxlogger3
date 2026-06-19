<?php

return [
    'name' => 'mxLogger',
    'name_lower' => 'mxlogger',
    'name_short' => 'mxl',
    'version' => '1.0.2',
    'release' => 'pl',
    'php_version' => '8.1',

    'paths' => [
        'core' => 'core/components/mxlogger/',
        'assets' => 'assets/components/mxlogger/',
    ],

    'elements' => [
        'category' => 'mxLogger',
        'settings' => 'elements/settings.php',
        'events' => 'elements/events.php',
        'snippets' => 'elements/snippets.php',
        'plugins' => 'elements/plugins.php',
        'menus' => 'elements/menus.php',
    ],

    'static' => [
        'chunks' => false,
        'snippets' => false,
        'plugins' => true,
    ],

    'encrypt' => false,

    'tools' => [
        'analyse' => 'vendor/bin/phpstan analyse --no-progress',
        'cs' => 'vendor/bin/php-cs-fixer fix',
        'csMode' => 'fix',
    ],

    'build' => [
        'download' => false,
        'install' => false,
        'update' => [
            'plugins' => true,
            'settings' => false,
            'events' => true,
            'menus' => true,
        ],
    ],
];
