<?php

/**
 * mxLogger bootstrap — регистрация xPDO-пакета модели и DI-сервиса.
 * Загружается ядром MODX 3 при инициализации namespace «mxlogger».
 * Доступ к сервису: $modx->services->get('mxlogger') или фасад $modx->mxl.
 *
 * @var \MODX\Revolution\modX $modx
 * @var array $namespace
 */

$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

$modx->addPackage('MxLogger\Model', $namespace['path'] . 'src/', null, 'MxLogger\\');

$modx->services->add('mxlogger', function ($c) use ($modx) {
    return new MxLogger\MxLogger($modx);
});

// Короткий фасад: $modx->mxl->info(...) из любого сниппета/плагина/чанка
// (без services->get()). xPDO помечен #[AllowDynamicProperties] — свойство
// безопасно в PHP 8.2+. Конструктор логгера дешёвый (настройки читаются лениво).
if (!isset($modx->mxl)) {
    $modx->mxl = $modx->services->get('mxlogger');
}
