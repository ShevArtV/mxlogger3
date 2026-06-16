<?php

return [
    'mxlogger.enabled' => [
        'xtype' => 'combo-boolean',
        'value' => '1',
        'area' => 'mxlogger_main',
    ],
    'mxlogger.min_level' => [
        'xtype' => 'textfield',
        'value' => 'debug',
        'area' => 'mxlogger_main',
    ],
    'mxlogger.capture_mode' => [
        'xtype' => 'textfield',
        'value' => 'auto',
        'area' => 'mxlogger_main',
    ],
    'mxlogger.tag_filter_mode' => [
        'xtype' => 'textfield',
        'value' => 'auto',
        'area' => 'mxlogger_main',
    ],
    'mxlogger.trace_limit' => [
        'xtype' => 'numberfield',
        'value' => '15',
        'area' => 'mxlogger_trace',
    ],
    'mxlogger.args_max_depth' => [
        'xtype' => 'numberfield',
        'value' => '3',
        'area' => 'mxlogger_trace',
    ],
    'mxlogger.args_max_string' => [
        'xtype' => 'numberfield',
        'value' => '512',
        'area' => 'mxlogger_trace',
    ],
    'mxlogger.args_max_items' => [
        'xtype' => 'numberfield',
        'value' => '50',
        'area' => 'mxlogger_trace',
    ],
    'mxlogger.filter_user' => [
        'xtype' => 'textfield',
        'value' => '',
        'area' => 'mxlogger_filter',
    ],
    'mxlogger.filter_usergroup' => [
        'xtype' => 'textfield',
        'value' => '',
        'area' => 'mxlogger_filter',
    ],
    'mxlogger.filter_session' => [
        'xtype' => 'textfield',
        'value' => '',
        'area' => 'mxlogger_filter',
    ],
    'mxlogger.filter_cookie' => [
        'xtype' => 'textfield',
        'value' => '',
        'area' => 'mxlogger_filter',
    ],
    'mxlogger.log_lifetime' => [
        'xtype' => 'numberfield',
        'value' => '604800',
        'area' => 'mxlogger_rotate',
    ],
    'mxlogger.rotate_interval' => [
        'xtype' => 'numberfield',
        'value' => '3600',
        'area' => 'mxlogger_rotate',
    ],
];
