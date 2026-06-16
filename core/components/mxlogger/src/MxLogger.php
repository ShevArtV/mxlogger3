<?php

declare(strict_types=1);

namespace MxLogger;

use MODX\Revolution\modX;
use MxLogger\Model\MxLoggerLog;

/**
 * mxLogger — удобное логирование процессов в MODX 3.
 *
 * Базовый сценарий:
 *   $mxl = $modx->services->get('mxlogger');
 *   $mxl->info('purchase', 'Корзина создана', ['cart' => $id]);
 *
 * Сценарий процесса (один экземпляр — один process_uid):
 *   $p = $mxl->process('purchase');
 *   $p->info('Старт оплаты', ['order' => 42]);
 *   $p->error('Платёж отклонён', ['code' => 'declined']);
 *
 * @package MxLogger
 */
class MxLogger
{
    public modX $modx;

    public array $config = [];

    /** Уровни и их числовой вес для сравнения с min_level. */
    public const LEVELS = [
        'debug'   => 10,
        'info'    => 20,
        'warning' => 30,
        'error'   => 40,
    ];

    /** @var bool|null Кэш результата проверки фильтра в рамках запроса. */
    protected ?bool $allowedCache = null;

    /** @var bool Флаг «мы внутри обработки события записи» — защита от рекурсии. */
    protected bool $inEvent = false;

    /** @var array Собственные классы — пропускаются при разборе backtrace. */
    protected array $ownClasses = [
        self::class,
        MxLoggerProcess::class,
    ];

    /**
     * @var array Диспетчерские кадры фреймворка, которые тоже пропускаем, чтобы
     * «Источник» указывал на реальный код, а не на механику вызова событий/плагинов.
     */
    protected array $noiseFrames = [
        'modScript::process',
        'modPlugin::process',
        'modElement::process',
        '::include', '::include_once', '::require', '::require_once', '::eval',
        '::call_user_func', '::call_user_func_array',
    ];

    public function __construct(modX $modx, array $config = [])
    {
        $this->modx = $modx;

        $corePath = $this->modx->getOption('mxlogger.core_path', $config, $this->modx->getOption('core_path') . 'components/mxlogger/');
        $assetsUrl = $this->modx->getOption('mxlogger.assets_url', $config, $this->modx->getOption('assets_url') . 'components/mxlogger/');

        $this->config = array_merge([
            'core_path'       => $corePath,
            'model_path'      => $corePath . 'src/',
            'processors_path' => $corePath . 'src/Processors/',
            'assets_url'      => $assetsUrl,
            'connector_url'   => $assetsUrl . 'connector.php',

            // Поведение логирования (берётся из системных настроек, переопределяется $config).
            'enabled'         => (bool) $this->modx->getOption('mxlogger.enabled', $config, true),
            'min_level'       => $this->modx->getOption('mxlogger.min_level', $config, 'debug'),
            'capture_mode'    => $this->modx->getOption('mxlogger.capture_mode', $config, 'auto'),
            'trace_limit'     => (int) $this->modx->getOption('mxlogger.trace_limit', $config, 15),
            'args_max_depth'  => (int) $this->modx->getOption('mxlogger.args_max_depth', $config, 3),
            'args_max_string' => (int) $this->modx->getOption('mxlogger.args_max_string', $config, 512),
            'args_max_items'  => (int) $this->modx->getOption('mxlogger.args_max_items', $config, 50),

            // Whitelist-фильтры (по умолчанию пусто — пишем всё).
            'filter_user'      => (string) $this->modx->getOption('mxlogger.filter_user', $config, ''),
            'filter_usergroup' => (string) $this->modx->getOption('mxlogger.filter_usergroup', $config, ''),
            'filter_session'   => (string) $this->modx->getOption('mxlogger.filter_session', $config, ''),
            'filter_cookie'    => (string) $this->modx->getOption('mxlogger.filter_cookie', $config, ''),
        ], $config);

        if (!isset($this->modx->packages['MxLogger\\Model'])) {
            $this->modx->addPackage('MxLogger\\Model', $this->config['model_path'], null, 'MxLogger\\');
        }
        // Лексикон нужен только для менеджерного UI; сервис может создаваться рано —
        // грузим аккуратно (lexicon может быть ещё не инициализирован).
        if ($this->modx->lexicon) {
            $this->modx->lexicon->load('mxlogger:default');
        }
    }

    /* ============================================================
     *  Публичный API
     * ============================================================ */

    /**
     * Записать лог.
     *
     * @param string|array $tags    Один или несколько тэгов (например 'purchase' или ['cart','purchase']).
     * @param string       $level   debug|info|warning|error.
     * @param string       $message Текст сообщения.
     * @param array        $context Произвольные данные.
     * @param array        $options Доп. опции: process_uid, trace (bool|'full'|'caller'), skip (int), skip_classes (array).
     * @return MxLoggerLog|null Сохранённый объект или null, если запись отфильтрована/выключена.
     */
    public function log($tags, string $level, string $message, array $context = [], array $options = []): ?MxLoggerLog
    {
        if (!$this->config['enabled']) {
            return null;
        }

        $level = $this->normalizeLevel($level);
        if (!$this->levelEnabled($level)) {
            return null;
        }
        if (!$this->isAllowed()) {
            return null;
        }

        $caller = $this->captureCaller($level, $options);

        $tagsArr = $this->normalizeTags($tags);
        $fields = [
            'tags'        => $this->wrapTags($tagsArr),
            'process_uid' => isset($options['process_uid']) ? (string) $options['process_uid'] : null,
            'level'       => $level,
            'message'     => (string) $message,
            'context'     => $context ?: null,
            'class'       => $caller['class'],
            'function'    => $caller['function'],
            'file'        => $caller['file'],
            'line'        => $caller['line'],
            'trace'       => $caller['trace'],
            'user_id'     => $this->getUserId(),
            'session_id'  => $this->getSessionId(),
            'ip'          => $this->getIp(),
            'createdon'   => time(),
        ];

        // Событие ДО записи: плагин может ОТМЕНИТЬ (returnedValues['prevent']=true)
        // или ИЗМЕНИТЬ любое поле. Гард inEvent — защита от рекурсии.
        if (!$this->inEvent) {
            $this->inEvent = true;
            $rv = null;
            try {
                $this->modx->invokeEvent('mxlOnBeforeLogSave', array_merge($fields, [
                    'tags_list' => $tagsArr,
                    'options'   => $options,
                    'mxlogger'  => $this,
                ]));
                if ($this->modx->event && $this->modx->event->name === 'mxlOnBeforeLogSave') {
                    $rv = $this->modx->event->returnedValues;
                }
            } catch (\Throwable $e) {
                $this->modx->log(modX::LOG_LEVEL_ERROR, '[mxLogger] Ошибка в обработчике mxlOnBeforeLogSave: ' . $e->getMessage());
            }
            $this->inEvent = false;

            if (is_array($rv) && !empty($rv)) {
                if (!empty($rv['prevent']) || !empty($rv['cancel'])) {
                    return null;
                }
                foreach ($rv as $k => $v) {
                    if (array_key_exists($k, $fields)) {
                        $fields[$k] = $v;
                    }
                }
                if (isset($rv['tags']) && is_array($rv['tags'])) {
                    $fields['tags'] = $this->wrapTags($this->normalizeTags($rv['tags']));
                }
            }
        }

        /** @var MxLoggerLog $log */
        $log = $this->modx->newObject(MxLoggerLog::class);
        $log->fromArray($fields, '', true, true);

        if ($log->save() === false) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[mxLogger] Не удалось сохранить запись лога для тэгов "' . $log->get('tags') . '"');
            return null;
        }

        // Событие ПОСЛЕ записи — для уведомлений. Ошибки обработчика не ломают логирование.
        if (!$this->inEvent) {
            $this->inEvent = true;
            try {
                $this->modx->invokeEvent('mxlOnAfterLogSave', array_merge($fields, [
                    'id'        => $log->get('id'),
                    'tags_list' => $this->unwrapTags($fields['tags']),
                    'object'    => $log,
                    'mxlogger'  => $this,
                ]));
            } catch (\Throwable $e) {
                $this->modx->log(modX::LOG_LEVEL_ERROR, '[mxLogger] Ошибка в обработчике mxlOnAfterLogSave: ' . $e->getMessage());
            }
            $this->inEvent = false;
        }

        return $log;
    }

    public function debug($tags, string $message, array $context = [], array $options = []): ?MxLoggerLog
    {
        return $this->log($tags, 'debug', $message, $context, $options);
    }

    public function info($tags, string $message, array $context = [], array $options = []): ?MxLoggerLog
    {
        return $this->log($tags, 'info', $message, $context, $options);
    }

    public function warning($tags, string $message, array $context = [], array $options = []): ?MxLoggerLog
    {
        return $this->log($tags, 'warning', $message, $context, $options);
    }

    public function error($tags, string $message, array $context = [], array $options = []): ?MxLoggerLog
    {
        return $this->log($tags, 'error', $message, $context, $options);
    }

    /**
     * Начать процесс — вернуть скоуп-логгер с фиксированными тэгами и process_uid.
     *
     * @param string|array $tags
     * @param string|null  $uid Если null — генерируется автоматически.
     * @return MxLoggerProcess
     */
    public function process($tags, ?string $uid = null): MxLoggerProcess
    {
        if ($uid === null) {
            $uid = $this->generateUid();
        }
        return new MxLoggerProcess($this, $tags, $uid);
    }

    /* ============================================================
     *  Тэги
     * ============================================================ */

    public function normalizeTags($tags): array
    {
        if (!is_array($tags)) {
            $tags = preg_split('/[\s,]+/', (string) $tags);
        }
        $out = [];
        foreach ($tags as $tag) {
            $tag = preg_replace('/[^a-z0-9]/', '', strtolower((string) $tag));
            if ($tag !== '' && !in_array($tag, $out, true)) {
                $out[] = $tag;
            }
        }
        return $out;
    }

    /** Обернуть тэги в CSV с граничными запятыми: ,cart,purchase, */
    public function wrapTags(array $tags): string
    {
        return empty($tags) ? '' : ',' . implode(',', $tags) . ',';
    }

    /** Развернуть CSV-обёртку тэгов обратно в массив. */
    public function unwrapTags($wrapped): array
    {
        $wrapped = trim((string) $wrapped, ',');
        return $wrapped === '' ? [] : explode(',', $wrapped);
    }

    /** Сгенерировать уникальный process_uid. */
    public function generateUid(): string
    {
        try {
            return bin2hex(random_bytes(8));
        } catch (\Exception $e) {
            return substr(md5(uniqid('mxl', true)), 0, 16);
        }
    }

    /* ============================================================
     *  Фильтрация записи (enabled / min_level / whitelist)
     * ============================================================ */

    protected function normalizeLevel($level): string
    {
        $level = strtolower((string) $level);
        return isset(self::LEVELS[$level]) ? $level : 'info';
    }

    protected function levelEnabled(string $level): bool
    {
        $min = $this->normalizeLevel($this->config['min_level']);
        return self::LEVELS[$level] >= self::LEVELS[$min];
    }

    /**
     * Разрешена ли запись логов по whitelist-фильтрам. Пусто = разрешено всё.
     */
    public function isAllowed(): bool
    {
        if ($this->allowedCache !== null) {
            return $this->allowedCache;
        }

        $filters = [
            'filter_user'      => trim($this->config['filter_user']),
            'filter_usergroup' => trim($this->config['filter_usergroup']),
            'filter_session'   => trim($this->config['filter_session']),
            'filter_cookie'    => trim($this->config['filter_cookie']),
        ];
        $active = array_filter($filters, 'strlen');
        if (empty($active)) {
            return $this->allowedCache = true;
        }

        $allowed = false;
        if (isset($active['filter_user']) && $this->matchUser($active['filter_user'])) {
            $allowed = true;
        }
        if (!$allowed && isset($active['filter_usergroup']) && $this->matchUserGroup($active['filter_usergroup'])) {
            $allowed = true;
        }
        if (!$allowed && isset($active['filter_session']) && $this->matchSession($active['filter_session'])) {
            $allowed = true;
        }
        if (!$allowed && isset($active['filter_cookie']) && $this->matchCookie($active['filter_cookie'])) {
            $allowed = true;
        }

        return $this->allowedCache = $allowed;
    }

    protected function matchUser($value): bool
    {
        $id = $this->getUserId();
        if (!$id) {
            return false;
        }
        $username = '';
        if ($user = $this->modx->user) {
            $username = (string) $user->get('username');
        }
        foreach (array_map('trim', explode(',', $value)) as $needle) {
            if ($needle === '') {
                continue;
            }
            if ((string) $needle === (string) $id || ($username !== '' && strcasecmp($needle, $username) === 0)) {
                return true;
            }
        }
        return false;
    }

    protected function matchUserGroup($value): bool
    {
        if (!$this->modx->user || !$this->getUserId()) {
            return false;
        }
        $needles = array_filter(array_map('trim', explode(',', $value)), 'strlen');
        if (empty($needles)) {
            return false;
        }
        foreach ($needles as $needle) {
            if (ctype_digit($needle)) {
                if ($this->userInGroupId((int) $needle)) {
                    return true;
                }
            } elseif ($this->modx->user->isMember($needle)) {
                return true;
            }
        }
        return false;
    }

    protected function userInGroupId($groupId): bool
    {
        $groups = $this->modx->user->getUserGroups();
        return in_array((int) $groupId, array_map('intval', $groups), true);
    }

    protected function matchSession($value): bool
    {
        $sid = $this->getSessionId();
        if ($sid === '') {
            return false;
        }
        foreach (array_map('trim', explode(',', $value)) as $needle) {
            if ($needle !== '' && $needle === $sid) {
                return true;
            }
        }
        return false;
    }

    protected function matchCookie($value): bool
    {
        foreach (array_map('trim', explode(',', $value)) as $rule) {
            if ($rule === '') {
                continue;
            }
            if (strpos($rule, '=') !== false) {
                [$name, $expected] = array_map('trim', explode('=', $rule, 2));
                if ($name !== '' && isset($_COOKIE[$name]) && (string) $_COOKIE[$name] === $expected) {
                    return true;
                }
            } elseif (isset($_COOKIE[$rule])) {
                return true;
            }
        }
        return false;
    }

    /* ============================================================
     *  Захват контекста вызова (backtrace)
     * ============================================================ */

    protected function captureCaller(string $level, array $options): array
    {
        $mode = $this->resolveCaptureMode($level, $options);
        $empty = ['class' => null, 'function' => null, 'file' => null, 'line' => null, 'trace' => null];
        if ($mode === 'off') {
            return $empty;
        }

        $withArgs = ($mode === 'full');
        $skip = isset($options['skip']) ? (int) $options['skip'] : 0;
        $extraClasses = !empty($options['skip_classes']) ? (array) $options['skip_classes'] : [];
        $btOptions = $withArgs ? 0 : DEBUG_BACKTRACE_IGNORE_ARGS;
        $limit = $withArgs ? 0 : ($this->config['trace_limit'] + 8);
        $frames = debug_backtrace($btOptions, $limit);

        $i = 0;
        $count = count($frames);
        while ($i < $count && $this->isInternalFrame($frames[$i], $extraClasses)) {
            $i++;
        }
        $i += $skip;
        if ($i >= $count) {
            return $empty;
        }

        $callerFrame = $frames[$i];
        $callSite = $i > 0 ? $frames[$i - 1] : $callerFrame;

        $result = [
            'class'    => $callerFrame['class'] ?? null,
            'function' => $callerFrame['function'] ?? null,
            'file'     => $callSite['file'] ?? null,
            'line'     => isset($callSite['line']) ? (int) $callSite['line'] : null,
            'trace'    => null,
        ];

        if ($withArgs) {
            $result['trace'] = [
                'params' => isset($callerFrame['args']) ? $this->sanitizeArgs($callerFrame['args']) : [],
                'stack'  => $this->buildStack(array_slice($frames, $i)),
            ];
        }

        return $result;
    }

    /**
     * Внутренний ли кадр (свой класс / диспетчерская механика) — такой пропускаем.
     *
     * @param array $extraClasses Точное имя класса или префикс ns со «\» на конце.
     */
    protected function isInternalFrame(array $frame, array $extraClasses = []): bool
    {
        $cls = $frame['class'] ?? '';
        $fn = $frame['function'] ?? '';

        if ($cls !== '') {
            if (in_array($cls, $this->ownClasses, true)) {
                return true;
            }
            foreach ($extraClasses as $skipClass) {
                if ($skipClass === '') {
                    continue;
                }
                if ($cls === $skipClass
                    || (substr($skipClass, -1) === '\\' && strpos($cls, $skipClass) === 0)) {
                    return true;
                }
            }
        }
        // Любой *::invokeEvent (modX, MiniShop3, …) — диспетчер событий.
        if ($fn === 'invokeEvent') {
            return true;
        }
        return in_array($cls . '::' . $fn, $this->noiseFrames, true);
    }

    protected function resolveCaptureMode(string $level, array $options): string
    {
        if (isset($options['trace'])) {
            if ($options['trace'] === true || $options['trace'] === 'full') {
                return 'full';
            }
            if ($options['trace'] === false) {
                return 'caller';
            }
            if (is_string($options['trace'])) {
                return $options['trace'];
            }
        }
        $mode = $this->config['capture_mode'];
        if ($mode === 'auto') {
            return self::LEVELS[$level] >= self::LEVELS['warning'] ? 'full' : 'caller';
        }
        return in_array($mode, ['off', 'caller', 'full'], true) ? $mode : 'caller';
    }

    protected function buildStack(array $frames): array
    {
        $stack = [];
        $limit = $this->config['trace_limit'];
        foreach ($frames as $frame) {
            if (count($stack) >= $limit) {
                break;
            }
            $fn = $frame['function'] ?? '';
            if (isset($frame['class'])) {
                $type = $frame['type'] ?? '::';
                $fn = $frame['class'] . $type . $fn;
            }
            $location = '';
            if (isset($frame['file'])) {
                $location = $frame['file'] . (isset($frame['line']) ? ':' . $frame['line'] : '');
            }
            $stack[] = ['call' => $fn, 'at' => $location];
        }
        return $stack;
    }

    /**
     * Рекурсивно привести аргументы к безопасному для хранения виду.
     *
     * @param mixed $value
     * @return mixed
     */
    public function sanitizeArgs($value, int $depth = 0)
    {
        if (is_object($value)) {
            if ($value instanceof \Closure) {
                return 'Closure';
            }
            return 'object(' . get_class($value) . ')';
        }
        if (is_resource($value)) {
            return 'resource(' . get_resource_type($value) . ')';
        }
        if (is_string($value)) {
            $max = $this->config['args_max_string'];
            if ($max > 0 && strlen($value) > $max) {
                return substr($value, 0, $max) . '…(' . strlen($value) . ')';
            }
            return $value;
        }
        if (is_array($value)) {
            if ($depth >= $this->config['args_max_depth']) {
                return '…array(' . count($value) . ')';
            }
            $out = [];
            $i = 0;
            foreach ($value as $k => $v) {
                if ($i++ >= $this->config['args_max_items']) {
                    $out['…'] = 'truncated(' . count($value) . ')';
                    break;
                }
                $out[$k] = $this->sanitizeArgs($v, $depth + 1);
            }
            return $out;
        }
        return $value;
    }

    /* ============================================================
     *  Идентификация запроса
     * ============================================================ */

    public function getUserId(): int
    {
        return $this->modx->user ? (int) $this->modx->user->get('id') : 0;
    }

    public function getSessionId(): string
    {
        $sid = session_id();
        return $sid !== false ? (string) $sid : '';
    }

    public function getIp(): string
    {
        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '';
    }
}
