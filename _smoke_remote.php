<?php
/**
 * Смоук-тест mxLogger на MODX 3 стенде. Запуск: php-8.3 _smoke_remote.php
 *  1) сервис достаётся из DI-контейнера ($modx->services->get('mxlogger'));
 *  2) запись лога с кириллицей пишется и читается обратно без искажений;
 *  3) таблица mxlogger_log существует и в utf8mb4.
 */

use MODX\Revolution\modX;

define('MODX_API_MODE', true);
require_once __DIR__ . '/config.core.php';
require_once MODX_CORE_PATH . 'vendor/autoload.php';

$modx = modX::getInstance('mxlsmoke');
$modx->initialize('web');
$modx->getService('lexicon', 'modLexicon');

echo "=== 1. Сервис из контейнера ===\n";
$mxl = null;
try {
    if ($modx->services->has('mxlogger')) {
        $mxl = $modx->services->get('mxlogger');
        echo "services->get('mxlogger') → " . get_class($mxl) . "\n";
    } else {
        echo "В контейнере нет — гружу bootstrap вручную (namespace мог не подняться в CLI)\n";
        $ns = $modx->getObject(\MODX\Revolution\modNamespace::class, ['name' => 'mxlogger']);
        $nsPath = $ns ? rtrim($ns->getCorePath(), '/') . '/' : (MODX_CORE_PATH . 'components/mxlogger/');
        $namespace = ['name' => 'mxlogger', 'path' => $nsPath];
        require $nsPath . 'bootstrap.php';
        $mxl = $modx->services->get('mxlogger');
        echo "после ручного bootstrap → " . get_class($mxl) . "\n";
    }
} catch (\Throwable $e) {
    echo "ОШИБКА получения сервиса: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== 2. Запись/чтение кириллицы ===\n";
$msg = 'Проверка кириллицы: ёжик, заказ №42';
$p = $mxl->process(['cart', 'purchase']);
$log = $p->info($msg, ['клиент' => 'Иванов', 'сумма' => 1500]);
if (!$log) {
    echo "ЗАПИСЬ ВЕРНУЛА null (отфильтровано/выключено?)\n";
    exit(1);
}
$id = $log->get('id');
echo "записано id={$id}, process_uid={$p->getUid()}\n";

// читаем заново из БД
$fresh = $modx->getObject(\MxLogger\Model\MxLoggerLog::class, $id);
$readMsg = $fresh ? $fresh->get('message') : '(не найдено)';
$readCtx = $fresh ? $fresh->get('context') : null;
echo "прочитано message: {$readMsg}\n";
echo "context: " . json_encode($readCtx, JSON_UNESCAPED_UNICODE) . "\n";
echo "tags: " . ($fresh ? $fresh->get('tags') : '') . " | class: " . ($fresh ? $fresh->get('class') : '') . "\n";
echo ($readMsg === $msg) ? "КИРИЛЛИЦА ОК (совпадает)\n" : "‼ КИРИЛЛИЦА ИСКАЖЕНА\n";

echo "\n=== 3. Charset таблицы ===\n";
$table = $modx->getTableName(\MxLogger\Model\MxLoggerLog::class);
$bare = trim((string) $table, '`');
$stmt = $modx->query("SHOW TABLE STATUS LIKE " . $modx->quote($bare));
$row = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : null;
echo "таблица {$table}, collation: " . ($row['Collation'] ?? '(нет)') . "\n";
echo (stripos((string)($row['Collation'] ?? ''), 'utf8mb4') !== false) ? "UTF8MB4 ОК\n" : "‼ НЕ utf8mb4\n";

echo "\n=== итог: записей в логе ===\n";
echo "всего: " . $modx->getCount(\MxLogger\Model\MxLoggerLog::class) . "\n";
echo "SMOKE DONE\n";
