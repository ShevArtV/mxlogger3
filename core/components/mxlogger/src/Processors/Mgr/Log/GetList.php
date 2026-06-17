<?php

declare(strict_types=1);

namespace MxLogger\Processors\Mgr\Log;

use MxLogger\Model\MxLoggerLog;
use MxLogger\Helpers\LogFilters;
use MODX\Revolution\modUser;
use MODX\Revolution\Processors\Model\GetListProcessor;
use xPDO\Om\xPDOObject;
use xPDO\Om\xPDOQuery;

/**
 * Список логов с фильтрами для грида.
 */
class GetList extends GetListProcessor
{
    public $classKey = MxLoggerLog::class;
    public $languageTopics = ['mxlogger:default'];
    public $defaultSortField = 'createdon';
    public $defaultSortDirection = 'DESC';
    public $checkListPermission = false;

    /** Реальные колонки таблицы — только по ним можно сортировать. */
    protected array $sortable = [
        'id', 'tags', 'process_uid', 'level', 'message', 'class', 'function',
        'file', 'line', 'user_id', 'session_id', 'ip', 'createdon',
    ];

    public function initialize()
    {
        $result = parent::initialize();
        // Защита от сортировки по вычисляемым колонкам — иначе SQL-ошибка.
        if (!in_array($this->getProperty('sort'), $this->sortable, true)) {
            $this->setProperty('sort', 'createdon');
        }
        return $result;
    }

    public function prepareQueryBeforeCount(xPDOQuery $c)
    {
        // Те же условия применяются и при очистке журнала (Clear) — единый
        // источник правды, чтобы очистка по фильтру совпадала с выборкой грида.
        $where = LogFilters::build($this->modx, $this->getProperties());
        if (!empty($where)) {
            $c->where($where);
        }
        return $c;
    }

    public function prepareRow(xPDOObject $object)
    {
        $array = $object->toArray();

        $array['createdon_formatted'] = $array['createdon']
            ? date('Y-m-d H:i:s', $array['createdon'])
            : '';

        $wrapped = trim((string) $array['tags'], ',');
        $array['tags_list'] = $wrapped === '' ? [] : explode(',', $wrapped);

        $array['caller'] = trim(($array['class'] ? $array['class'] . '::' : '') . $array['function']);
        $array['source'] = $array['file'] ? $array['file'] . ($array['line'] ? ':' . $array['line'] : '') : '';

        $username = '';
        if (!empty($array['user_id'])) {
            if ($user = $this->modx->getObject(modUser::class, (int) $array['user_id'])) {
                $username = $user->get('username');
            }
        }
        $array['username'] = $username;

        // Объёмные поля в грид-строке не тащим целиком — они нужны в окне детали.
        $array['message_short'] = mb_strlen((string) $array['message']) > 160
            ? mb_substr((string) $array['message'], 0, 160) . '…'
            : (string) $array['message'];
        unset($array['context'], $array['trace']);

        return $array;
    }
}
