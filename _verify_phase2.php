<?php
/**
 * Backend-смоук Фазы 2 (CMP): меню + процессоры грида. Запуск на стенде:
 *   php-8.3 _verify_phase2.php
 */

use MODX\Revolution\modX;
use MODX\Revolution\modMenu;

define('MODX_API_MODE', true);
require_once __DIR__ . '/config.core.php';
require_once MODX_CORE_PATH . 'vendor/autoload.php';

$modx = modX::getInstance('mxlp2');
$modx->initialize('mgr');
$modx->getService('lexicon', 'modLexicon');
$modx->lexicon->load('mxlogger:default');

echo "=== 1. Пункт меню ===\n";
$menu = $modx->getObject(modMenu::class, ['action' => 'logs', 'namespace' => 'mxlogger']);
if (!$menu) {
    $menu = $modx->getObject(modMenu::class, ['text' => 'mxlogger']);
}
echo $menu
    ? "меню ЕСТЬ: text={$menu->get('text')}, parent={$menu->get('parent')}, action={$menu->get('action')}, ns={$menu->get('namespace')}\n"
    : "‼ меню НЕ найдено\n";

echo "\n=== 2. Процессор GetList ===\n";
$res = $modx->runProcessor('MxLogger\\Processors\\Mgr\\Log\\GetList', ['limit' => 10, 'sort' => 'createdon', 'dir' => 'DESC']);
if ($res->isError()) {
    echo "‼ GetList ошибка: " . $res->getMessage() . "\n";
} else {
    $data = json_decode($res->getResponse(), true);
    echo "total={$data['total']}, строк=" . count($data['results'] ?? []) . "\n";
    if (!empty($data['results'])) {
        $r = $data['results'][0];
        echo "  первая: [{$r['level']}] {$r['message_short']} | тэги=" . implode(',', $r['tags_list'] ?? []) . " | {$r['createdon_formatted']}\n";
        echo "  поля грида: " . implode(', ', array_keys($r)) . "\n";
    }
}

echo "\n=== 3. Процессор GetList с фильтром по тэгу cart ===\n";
$res = $modx->runProcessor('MxLogger\\Processors\\Mgr\\Log\\GetList', ['limit' => 5, 'tags' => 'cart']);
$data = json_decode($res->getResponse(), true);
echo "по тэгу 'cart': total={$data['total']}\n";

echo "\n=== 4. Процессор GetTags ===\n";
$res = $modx->runProcessor('MxLogger\\Processors\\Mgr\\Log\\GetTags', []);
$data = json_decode($res->getResponse(), true);
$tags = array_map(fn($t) => $t['tag'], $data['results'] ?? []);
echo "уникальные тэги: " . implode(', ', $tags) . "\n";

echo "\n=== 5. Процессор Get (деталь id=1) ===\n";
$res = $modx->runProcessor('MxLogger\\Processors\\Mgr\\Log\\Get', ['id' => 1]);
if ($res->isError()) {
    echo "‼ Get ошибка: " . $res->getMessage() . "\n";
} else {
    $data = json_decode($res->getResponse(), true);
    $o = $data['object'] ?? [];
    echo "message: " . ($o['message'] ?? '') . "\n";
    echo "context_pretty: " . str_replace("\n", ' ', $o['context_pretty'] ?? '') . "\n";
}

echo "\nPHASE2 VERIFY DONE\n";
