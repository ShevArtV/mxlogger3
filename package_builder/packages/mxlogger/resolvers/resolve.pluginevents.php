<?php
/**
 * Resolver: привязки плагинов mxLogger к событиям — priority -10 на install и upgrade (идемпотентно).
 *
 * Логгер должен идти первым в цепочке: modX::invokeEvent() сбрасывает returnedValues
 * перед каждым плагином, а магазин читает их после всей цепочки. Логгер в конце
 * затирал данные плагина сайта. Резолвер правит и привязки, уже стоящие на сайте.
 *
 * @var \xPDO\Transport\xPDOTransport $transport
 * @var array $options
 */

use xPDO\Transport\xPDOTransport;
use MODX\Revolution\modX;
use MODX\Revolution\modContext;
use MODX\Revolution\modPlugin;
use MODX\Revolution\modPluginEvent;

if (!$transport->xpdo) {
    return true;
}

/** @var modX $modx */
$modx = $transport->xpdo;
$action = $options[xPDOTransport::PACKAGE_ACTION] ?? '';

if ($action === xPDOTransport::ACTION_UNINSTALL) {
    return true;
}

$priority = -10;
$total = 0;
$fixed = 0;
foreach (['mxLoggerRotate', 'mxLoggerMiniShop3'] as $name) {
    $plugin = $modx->getObject(modPlugin::class, ['name' => $name]);
    if (!$plugin) {
        continue;
    }
    /** @var modPluginEvent $pe */
    foreach ($modx->getIterator(modPluginEvent::class, ['pluginid' => $plugin->get('id')]) as $pe) {
        $total++;
        if ((int)$pe->get('priority') === $priority) {
            continue;
        }
        $pe->set('priority', $priority);
        if ($pe->save()) {
            $fixed++;
        }
    }
}
$modx->log(modX::LOG_LEVEL_INFO, "[mxlogger] Привязки плагинов к событиям: priority {$priority} у {$total}, исправлено {$fixed}.");

// eventMap лежит в кэше контекстов (context_settings) — пересобираем его всегда:
// на upgrade priority мог переписать уже vehicle плагинов (fixed = 0), а кэш остался старым.
if ($total > 0) {
    $q = $modx->newQuery(modContext::class);
    $q->select($modx->escape('key'));
    $keys = $q->prepare() && $q->stmt->execute() ? $q->stmt->fetchAll(PDO::FETCH_COLUMN) : [];
    if (!empty($keys)) {
        $modx->cacheManager->refresh(['context_settings' => ['contexts' => $keys]]);
    }
}

return true;
