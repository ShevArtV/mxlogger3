<?php

declare(strict_types=1);

namespace MxLogger\Model\mysql;

class MxLoggerLog extends \MxLogger\Model\MxLoggerLog
{
    public static $metaMap = [
        'package' => 'MxLogger\\Model',
        'version' => '3.0',
        'table' => 'mxlogger_log',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' => [
            'engine' => 'InnoDB',
        ],
        'fields' => [
            'tags' => '',
            'process_uid' => '',
            'level' => 'info',
            'message' => null,
            'context' => null,
            'class' => '',
            'function' => '',
            'file' => '',
            'line' => 0,
            'trace' => null,
            'user_id' => 0,
            'session_id' => '',
            'ip' => '',
            'createdon' => 0,
        ],
        'fieldMeta' => [
            'tags' => [
                'dbtype' => 'varchar',
                'precision' => '500',
                'phptype' => 'string',
                'null' => false,
                'default' => '',
                'index' => 'fulltext',
            ],
            'process_uid' => [
                'dbtype' => 'varchar',
                'precision' => '64',
                'phptype' => 'string',
                'null' => true,
                'default' => '',
                'index' => 'index',
            ],
            'level' => [
                'dbtype' => 'varchar',
                'precision' => '20',
                'phptype' => 'string',
                'null' => false,
                'default' => 'info',
                'index' => 'index',
            ],
            'message' => [
                'dbtype' => 'text',
                'phptype' => 'string',
                'null' => true,
            ],
            'context' => [
                'dbtype' => 'mediumtext',
                'phptype' => 'json',
                'null' => true,
            ],
            'class' => [
                'dbtype' => 'varchar',
                'precision' => '190',
                'phptype' => 'string',
                'null' => true,
                'default' => '',
                'index' => 'index',
            ],
            'function' => [
                'dbtype' => 'varchar',
                'precision' => '190',
                'phptype' => 'string',
                'null' => true,
                'default' => '',
            ],
            'file' => [
                'dbtype' => 'varchar',
                'precision' => '255',
                'phptype' => 'string',
                'null' => true,
                'default' => '',
            ],
            'line' => [
                'dbtype' => 'integer',
                'precision' => '11',
                'attributes' => 'unsigned',
                'phptype' => 'integer',
                'null' => true,
                'default' => 0,
            ],
            'trace' => [
                'dbtype' => 'mediumtext',
                'phptype' => 'json',
                'null' => true,
            ],
            'user_id' => [
                'dbtype' => 'integer',
                'precision' => '11',
                'attributes' => 'unsigned',
                'phptype' => 'integer',
                'null' => false,
                'default' => 0,
                'index' => 'index',
            ],
            'session_id' => [
                'dbtype' => 'varchar',
                'precision' => '64',
                'phptype' => 'string',
                'null' => true,
                'default' => '',
                'index' => 'index',
            ],
            'ip' => [
                'dbtype' => 'varchar',
                'precision' => '45',
                'phptype' => 'string',
                'null' => true,
                'default' => '',
            ],
            'createdon' => [
                'dbtype' => 'integer',
                'precision' => '20',
                'attributes' => 'unsigned',
                'phptype' => 'integer',
                'null' => false,
                'default' => 0,
                'index' => 'index',
            ],
        ],
        'indexes' => [
            'tags' => [
                'alias' => 'tags',
                'primary' => false,
                'unique' => false,
                'type' => 'FULLTEXT',
                'columns' => [
                    'tags' => ['length' => '', 'collation' => 'A', 'null' => false],
                ],
            ],
            'process_uid' => [
                'alias' => 'process_uid',
                'primary' => false,
                'unique' => false,
                'type' => 'BTREE',
                'columns' => [
                    'process_uid' => ['length' => '', 'collation' => 'A', 'null' => false],
                ],
            ],
            'level' => [
                'alias' => 'level',
                'primary' => false,
                'unique' => false,
                'type' => 'BTREE',
                'columns' => [
                    'level' => ['length' => '', 'collation' => 'A', 'null' => false],
                ],
            ],
            'class' => [
                'alias' => 'class',
                'primary' => false,
                'unique' => false,
                'type' => 'BTREE',
                'columns' => [
                    'class' => ['length' => '', 'collation' => 'A', 'null' => false],
                ],
            ],
            'user_id' => [
                'alias' => 'user_id',
                'primary' => false,
                'unique' => false,
                'type' => 'BTREE',
                'columns' => [
                    'user_id' => ['length' => '', 'collation' => 'A', 'null' => false],
                ],
            ],
            'session_id' => [
                'alias' => 'session_id',
                'primary' => false,
                'unique' => false,
                'type' => 'BTREE',
                'columns' => [
                    'session_id' => ['length' => '', 'collation' => 'A', 'null' => false],
                ],
            ],
            'createdon' => [
                'alias' => 'createdon',
                'primary' => false,
                'unique' => false,
                'type' => 'BTREE',
                'columns' => [
                    'createdon' => ['length' => '', 'collation' => 'A', 'null' => false],
                ],
            ],
        ],
    ];
}
