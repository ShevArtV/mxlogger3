<?php

declare(strict_types=1);

namespace MxLogger\Processors\Mgr\Log;

use MxLogger\Model\MxLoggerLog;
use MxLogger\Helpers\TagFilter;
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
        $level = $this->getProperty('level');
        $processUid = $this->getProperty('process_uid');
        $userId = $this->getProperty('user_id');
        $class = $this->getProperty('class');
        $query = $this->getProperty('query');
        $dateFrom = $this->getProperty('date_from');
        $dateTo = $this->getProperty('date_to');

        TagFilter::apply(
            $this->modx,
            $c,
            $this->getProperty('tags', $this->getProperty('tag')),
            $this->getProperty('tags_match', 'any')
        );

        if (!empty($level)) {
            $c->where(['level' => $level]);
        }
        if (!empty($processUid)) {
            $c->where(['process_uid' => $processUid]);
        }
        if ($userId !== null && $userId !== '') {
            $c->where(['user_id' => (int) $userId]);
        }
        if (!empty($class)) {
            $c->where(['class:LIKE' => '%' . $class . '%']);
        }
        if (!empty($dateFrom) && ($tsFrom = strtotime($dateFrom))) {
            $c->where(['createdon:>=' => $tsFrom]);
        }
        if (!empty($dateTo) && ($tsTo = strtotime($dateTo))) {
            $c->where(['createdon:<=' => $tsTo]);
        }
        if (!empty($query)) {
            // Поиск по тексту: сообщение, источник (класс/метод), файл/строка.
            $q = $this->modx->quote('%' . $query . '%');
            $c->where('(' .
                'message LIKE ' . $q .
                ' OR class LIKE ' . $q .
                ' OR function LIKE ' . $q .
                ' OR file LIKE ' . $q .
                ' OR CAST(line AS CHAR) LIKE ' . $q .
                ' OR CONCAT(class, \'::\', function) LIKE ' . $q .
                ' OR CONCAT(file, \':\', line) LIKE ' . $q .
            ')');
        }

        // Отдельный фильтр по пользователю / сессии / ip (AND к остальным).
        $ident = $this->getProperty('ident');
        if (!empty($ident)) {
            $q = $this->modx->quote('%' . $ident . '%');
            $usersTable = $this->modx->getTableName(modUser::class);
            $conds = [
                'session_id LIKE ' . $q,
                'ip LIKE ' . $q,
                'user_id IN (SELECT id FROM ' . $usersTable . ' WHERE username LIKE ' . $q . ')',
            ];
            if (ctype_digit((string) $ident)) {
                $conds[] = 'user_id = ' . (int) $ident;
            }
            $c->where('(' . implode(' OR ', $conds) . ')');
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
