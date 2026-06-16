<?php

declare(strict_types=1);

namespace MxLogger\Processors\Mgr\Log;

use MxLogger\Model\MxLoggerLog;
use MODX\Revolution\Processors\Processor;

/**
 * Список уникальных тэгов — для комбобокса фильтра.
 * Тэги хранятся в CSV-колонке tags, разворачиваем и схлопываем в PHP.
 */
class GetTags extends Processor
{
    public $languageTopics = ['mxlogger:default'];

    public function process()
    {
        $query = preg_replace('/[^a-z0-9]/', '', strtolower((string) $this->getProperty('query', '')));

        $c = $this->modx->newQuery(MxLoggerLog::class);
        $c->where(['tags:!=' => '']);
        if ($query !== '') {
            $c->where(['tags:LIKE' => '%' . $query . '%']);
        }
        $c->groupby('tags');
        $c->select(['tags']);

        $unique = [];
        if ($c->prepare() && $c->stmt->execute()) {
            foreach ($c->stmt->fetchAll(\PDO::FETCH_COLUMN) as $wrapped) {
                $wrapped = trim((string) $wrapped, ',');
                if ($wrapped === '') {
                    continue;
                }
                foreach (explode(',', $wrapped) as $tag) {
                    if ($tag === '' || isset($unique[$tag])) {
                        continue;
                    }
                    if ($query !== '' && strpos($tag, $query) === false) {
                        continue;
                    }
                    $unique[$tag] = true;
                }
            }
        }

        ksort($unique);
        $tags = [];
        foreach (array_keys($unique) as $tag) {
            $tags[] = ['tag' => $tag];
        }

        return $this->outputArray($tags, count($tags));
    }
}
