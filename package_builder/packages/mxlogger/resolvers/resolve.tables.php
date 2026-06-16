<?php
/**
 * Resolver: создание/обновление таблицы mxlogger_log при install/upgrade.
 *
 * Таблица НЕ дропается при uninstall (чтобы не потерять логи).
 * Charset с порога enforced в utf8mb4 — иначе createObjectContainer создаёт таблицу
 * в дефолтном charset сервера (latin1) и вставка кириллицы падает (Error 1366),
 * см. инцидент charset в БЗ.
 *
 * @var \xPDO\Transport\xPDOTransport $transport
 * @var array $options
 */

use xPDO\Transport\xPDOTransport;
use MODX\Revolution\modX;

if (!$transport->xpdo) {
    return true;
}

/** @var modX $modx */
$modx = $transport->xpdo;
$action = $options[xPDOTransport::PACKAGE_ACTION] ?? '';

if ($action === xPDOTransport::ACTION_UNINSTALL) {
    return true;
}

$corePath = $modx->getOption('core_path') . 'components/mxlogger/';

$autoload = $corePath . 'vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

if (!isset($modx->packages['MxLogger\\Model'])) {
    $modx->addPackage('MxLogger\\Model', $corePath . 'src/', null, 'MxLogger\\');
}

$manager = $modx->getManager();
$manager->createObjectContainer(\MxLogger\Model\MxLoggerLog::class);

// Enforce utf8mb4 на таблице (createObjectContainer мог создать её в latin1).
try {
    $table = $modx->getTableName(\MxLogger\Model\MxLoggerLog::class);
    if ($table) {
        $bare = trim($table, '`');
        $charset = $modx->getOption('mysql_string_charset', null, 'utf8mb4');
        if (stripos((string) $charset, 'utf8mb4') === false) {
            $charset = 'utf8mb4';
        }
        $stmt = $modx->query("SHOW TABLE STATUS LIKE " . $modx->quote($bare));
        $row = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : null;
        $collation = $row['Collation'] ?? '';
        if (stripos((string) $collation, 'utf8mb4') === false) {
            $modx->exec("ALTER TABLE {$table} CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $modx->log(modX::LOG_LEVEL_INFO, '[mxLogger] Таблица mxlogger_log приведена к utf8mb4.');
        }
    }
} catch (\Throwable $e) {
    $modx->log(modX::LOG_LEVEL_WARN, '[mxLogger] Не удалось enforce utf8mb4: ' . $e->getMessage());
}

$modx->log(modX::LOG_LEVEL_INFO, '[mxLogger] Таблица mxlogger_log проверена/создана.');

return true;
