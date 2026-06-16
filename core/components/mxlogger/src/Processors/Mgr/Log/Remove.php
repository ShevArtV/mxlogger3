<?php

declare(strict_types=1);

namespace MxLogger\Processors\Mgr\Log;

use MxLogger\Model\MxLoggerLog;
use MODX\Revolution\Processors\Model\RemoveProcessor;

/**
 * Удалить запись лога.
 */
class Remove extends RemoveProcessor
{
    public $classKey = MxLoggerLog::class;
    public $languageTopics = ['mxlogger:default'];
    public $objectType = 'mxlogger.log';
    public $checkRemovePermission = false;
}
