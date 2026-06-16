<?php

declare(strict_types=1);

namespace MxLogger\Processors\Mgr\Log;

use MxLogger\Model\MxLoggerLog;
use MODX\Revolution\modUser;
use MODX\Revolution\Processors\Model\GetProcessor;

/**
 * Одна запись лога (для окна детали).
 */
class Get extends GetProcessor
{
    public $classKey = MxLoggerLog::class;
    public $languageTopics = ['mxlogger:default'];
    public $objectType = 'mxlogger.log';
    public $checkViewPermission = false;

    public function cleanup()
    {
        $array = $this->object->toArray();
        $array['createdon_formatted'] = $array['createdon']
            ? date('Y-m-d H:i:s', $array['createdon'])
            : '';
        $wrapped = trim((string) $array['tags'], ',');
        $array['tags_list'] = $wrapped === '' ? [] : explode(',', $wrapped);
        $array['tags_display'] = implode(', ', $array['tags_list']);

        $array['caller'] = trim(($array['class'] ? $array['class'] . '::' : '') . $array['function']);
        $array['source'] = $array['file'] ? $array['file'] . ($array['line'] ? ':' . $array['line'] : '') : '';

        $array['context_pretty'] = $this->prettyJson($this->object->get('context'));
        $array['trace_pretty'] = $this->prettyJson($this->object->get('trace'));

        if (!empty($array['user_id']) && ($user = $this->modx->getObject(modUser::class, (int) $array['user_id']))) {
            $array['username'] = $user->get('username');
        } else {
            $array['username'] = '';
        }

        return $this->success('', $array);
    }

    protected function prettyJson($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = $decoded === null ? $value : $decoded;
        }
        return (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
