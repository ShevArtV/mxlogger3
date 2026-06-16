<?php

declare(strict_types=1);

namespace MxLogger\Processors\Mgr\Log;

use MxLogger\Model\MxLoggerLog;
use MxLogger\Helpers\TagFilter;
use MODX\Revolution\Processors\Processor;

/**
 * Очистка журнала. Уважает текущие фильтры грида, если они переданы:
 *   tags (tag), tags_match, level, process_uid, user_id, date_from, date_to.
 * Без фильтров — очищает весь журнал.
 */
class Clear extends Processor
{
    public $languageTopics = ['mxlogger:default'];

    public function process()
    {
        // Гарантируем загрузку топика — через коннектор run() не всегда успевает
        // подгрузить его до process(), и lexicon() возвращал бы голый ключ.
        $this->modx->lexicon->load('mxlogger:default');

        $where = [];

        $clause = TagFilter::clause(
            $this->modx,
            $this->getProperty('tags', $this->getProperty('tag')),
            $this->getProperty('tags_match', 'any')
        );
        if ($clause !== '') {
            $where[] = $clause;
        }

        $level = $this->getProperty('level');
        if (!empty($level)) {
            $where['level'] = $level;
        }
        $processUid = $this->getProperty('process_uid');
        if (!empty($processUid)) {
            $where['process_uid'] = $processUid;
        }
        $userId = $this->getProperty('user_id');
        if ($userId !== null && $userId !== '') {
            $where['user_id'] = (int) $userId;
        }
        $dateFrom = $this->getProperty('date_from');
        if (!empty($dateFrom) && ($tsFrom = strtotime($dateFrom))) {
            $where['createdon:>='] = $tsFrom;
        }
        $dateTo = $this->getProperty('date_to');
        if (!empty($dateTo) && ($tsTo = strtotime($dateTo))) {
            $where['createdon:<='] = $tsTo;
        }

        if (empty($where)) {
            // Полная очистка — быстрее прямым DELETE без выборки.
            $removed = $this->modx->exec('DELETE FROM ' . $this->modx->getTableName(MxLoggerLog::class));
            $removed = ($removed === false) ? 0 : (int) $removed;
        } else {
            $removed = (int) $this->modx->removeCollection(MxLoggerLog::class, $where);
        }

        return $this->success(
            $this->modx->lexicon('mxlogger_log_cleared', ['count' => $removed]),
            ['removed' => $removed]
        );
    }
}
