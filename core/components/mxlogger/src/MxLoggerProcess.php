<?php

declare(strict_types=1);

namespace MxLogger;

use MxLogger\Model\MxLoggerLog;

/**
 * mxLoggerProcess — скоуп логирования одного экземпляра процесса.
 * Фиксирует тэги и process_uid, чтобы все записи одного процесса
 * (например одной покупки) шли с общим идентификатором и по порядку.
 *
 * @package MxLogger
 */
class MxLoggerProcess
{
    protected MxLogger $logger;

    /** @var string|array */
    protected $tags;

    protected string $uid;

    public function __construct(MxLogger $logger, $tags, string $uid)
    {
        $this->logger = $logger;
        $this->tags = $tags;
        $this->uid = $uid;
    }

    /** Идентификатор экземпляра процесса (process_uid). */
    public function getUid(): string
    {
        return $this->uid;
    }

    /**
     * Тэги процесса (как переданы при создании).
     *
     * @return string|array
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * Добавить тэг к процессу (применится к последующим записям).
     *
     * @return $this
     */
    public function addTag(string $tag): self
    {
        $tags = $this->logger->normalizeTags($this->tags);
        foreach ($this->logger->normalizeTags($tag) as $t) {
            if (!in_array($t, $tags, true)) {
                $tags[] = $t;
            }
        }
        $this->tags = $tags;
        return $this;
    }

    public function log(string $level, string $message, array $context = [], array $options = []): ?MxLoggerLog
    {
        $options['process_uid'] = $this->uid;
        return $this->logger->log($this->tags, $level, $message, $context, $options);
    }

    public function debug(string $message, array $context = [], array $options = []): ?MxLoggerLog
    {
        return $this->log('debug', $message, $context, $options);
    }

    public function info(string $message, array $context = [], array $options = []): ?MxLoggerLog
    {
        return $this->log('info', $message, $context, $options);
    }

    public function warning(string $message, array $context = [], array $options = []): ?MxLoggerLog
    {
        return $this->log('warning', $message, $context, $options);
    }

    public function error(string $message, array $context = [], array $options = []): ?MxLoggerLog
    {
        return $this->log('error', $message, $context, $options);
    }
}
