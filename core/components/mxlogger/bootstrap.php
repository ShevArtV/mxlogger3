<?php

/**
 * mxLogger bootstrap — регистрация xPDO-пакета модели и DI-сервиса.
 * Загружается ядром MODX 3 при инициализации namespace «mxlogger».
 * Доступ к сервису из любого кода: $modx->services->get('mxlogger').
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
