<?php

declare(strict_types=1);

namespace MxLogger\Processors\Mgr\Log;

use MxLogger\Model\MxLoggerLog;
use MxLogger\Helpers\LogFilters;
use MODX\Revolution\Processors\Processor;

/**
 * Очистка журнала. Уважает текущие фильтры грида, если они переданы:
 *   tags (tag), tags_match, level, process_uid, user_id, class,
 *   date_from, date_to, query, ident.
 * Набор условий строится тем же построителем, что и выборка грида
 * (LogFilters) — поэтому очистка по фильтру удаляет ровно то, что в гриде
 * видно. Без фильтров — очищает весь журнал.
 */
class Clear extends Processor
{
    public $languageTopics = ['mxlogger:default'];

    public function process()
    {
        // Гарантируем загрузку топика — через коннектор run() не всегда успевает
        // подгрузить его до process(), и lexicon() возвращал бы голый ключ.
        $this->modx->lexicon->load('mxlogger:default');

        $where = LogFilters::build($this->modx, $this->getProperties());

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
