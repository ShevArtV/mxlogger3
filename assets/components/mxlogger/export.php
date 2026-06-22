<?php

/**
 * Потоковый экспорт журнала mxLogger в текстовый файл (.md / .txt).
 *
 * Не процессор: отдаёт файл напрямую (Content-Disposition: attachment),
 * а не JSON — поэтому живёт отдельным эндпоинтом рядом с connector.php.
 * Авторизация — по сессии менеджера (GET-навигация из грида), доступ только
 * аутентифицированному менеджеру.
 *
 * Фильтры берутся из тех же GET-параметров, что и грид, и прогоняются через
 * LogFilters::build() — единый источник правды. Поэтому экспорт отдаёт ровно
 * те записи, что видны в гриде (и что удалила бы очистка по тому же фильтру).
 * Без фильтров — весь журнал.
 *
 * Рендер строк — в LogExporter (src/Helpers/LogExporter.php), его гоняет тест.
 * Выборка идёт батчами и пишется в php://output потоком.
 *
 * @package mxlogger
 */
@set_time_limit(0);

require_once dirname(__DIR__, 3) . '/config.core.php';
require_once MODX_CORE_PATH . 'vendor/autoload.php';

$componentAutoload = MODX_CORE_PATH . 'components/mxlogger/vendor/autoload.php';
if (is_file($componentAutoload)) {
    require_once $componentAutoload;
}

/** @var \MODX\Revolution\modX $modx */
$modx = new \MODX\Revolution\modX();
$modx->initialize('mgr');
$modx->lexicon->load('mxlogger:default');

// Доступ только авторизованному менеджеру.
if (!$modx->user || !$modx->user->isAuthenticated('mgr')) {
    header('HTTP/1.1 401 Unauthorized');
    exit('Unauthorized');
}

// Формат: md (по умолчанию) или txt — нормализуется внутри экспортёра.
$format = isset($_GET['format']) ? strtolower(trim((string) $_GET['format'])) : 'md';
$exporter = new \MxLogger\Helpers\LogExporter($modx, $format);

// Свойства фильтра — те же ключи, что принимает GetList/Clear.
$filterKeys = [
    'tags', 'tag', 'tags_match', 'level', 'process_uid', 'user_id',
    'class', 'date_from', 'date_to', 'query', 'ident',
];
$props = [];
foreach ($filterKeys as $k) {
    if (isset($_GET[$k])) {
        $props[$k] = $_GET[$k];
    }
}
$where = \MxLogger\Helpers\LogFilters::build($modx, $props);

// Сколько записей попадёт в выгрузку (для шапки файла).
$total = (int) $modx->getCount(\MxLogger\Model\MxLoggerLog::class, empty($where) ? null : $where);

// Человекочитаемое описание активных фильтров для шапки.
$activeFilters = [];
foreach (['tags', 'level', 'process_uid', 'ident', 'query', 'class', 'date_from', 'date_to', 'user_id'] as $key) {
    if (isset($props[$key]) && trim((string) $props[$key]) !== '') {
        $activeFilters[] = $key . '=' . trim((string) $props[$key]);
    }
}
$filterText = $activeFilters
    ? implode('; ', $activeFilters)
    : $modx->lexicon('mxlogger_export_nofilter');

// --- Отдаём файл потоком -----------------------------------------------

$filename = 'mxlogger-' . date('Ymd-His') . '.' . $exporter->getExtension();

while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: ' . $exporter->getMime() . '; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate');

$out = fopen('php://output', 'w');
fwrite($out, $exporter->renderHeader($total, $filterText, date('Y-m-d H:i:s')));

// Хронологический порядок (старые → новые) — естественно для чтения журнала.
$batch = 1000;
$offset = 0;
do {
    $c = $modx->newQuery(\MxLogger\Model\MxLoggerLog::class);
    if (!empty($where)) {
        $c->where($where);
    }
    $c->sortby('createdon', 'ASC');
    $c->sortby('id', 'ASC');
    $c->limit($batch, $offset);

    // getCollection корректно формирует SELECT-колонки и гидрирует объекты —
    // toArray() даёт те же поля, что и грид (GetList::prepareRow).
    $objects = $modx->getCollection(\MxLogger\Model\MxLoggerLog::class, $c);
    foreach ($objects as $object) {
        fwrite($out, $exporter->renderRow($object->toArray()));
    }
    flush();

    $fetched = count($objects);
    $offset += $batch;
} while ($fetched === $batch);

fclose($out);
exit;
