<?php

/**
 * Коннектор mxLogger — точка входа AJAX-запросов менеджерного грида.
 * Параметр `action` — FQCN процессора (например
 * MxLogger\Processors\Mgr\Log\GetList); ядро резолвит через autoload.
 */

require_once dirname(__DIR__, 3) . '/config.core.php';
require_once MODX_CORE_PATH . 'config/' . MODX_CONFIG_KEY . '.inc.php';
require_once MODX_CONNECTORS_PATH . 'index.php';

$autoload = MODX_CORE_PATH . 'components/mxlogger/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

/** @var \MODX\Revolution\modX $modx */
$modx->request->handleRequest();
