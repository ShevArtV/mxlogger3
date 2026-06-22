<?php

/**
 * Тест рендера экспорта журнала (MxLogger\Helpers\LogExporter) под MODX 3.
 *
 * Прогоняется на стенде с живой БД — проверяет логику в обход HTTP/авторизации:
 *   - decodeJson: массив/строка/пусто → корректная строка, без литерала «Array»;
 *   - renderRow на реальных записях: id/уровень/поля заполнены;
 *   - JSON-поля context/trace рендерятся как JSON, а не «Array».
 *
 * Запуск (одно SSH-подключение):
 *   ssh hostland 'php8.x' < package_builder/packages/mxlogger/tests/test_export.php
 *   (корень MODX — env MXLOGGER_BASE или argv[1], дефолт — стенд modx3 Hostland)
 */
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '1');

$base = getenv('MXLOGGER_BASE');
if (!$base && isset($argv[1])) {
    $base = $argv[1];
}
if (!$base) {
    $base = '/home/host1860015/modx3.art-sites.ru/htdocs/www/';
}
$base = rtrim($base, '/') . '/';

require_once $base . 'config.core.php';
require_once MODX_CORE_PATH . 'vendor/autoload.php';

$componentAutoload = MODX_CORE_PATH . 'components/mxlogger/vendor/autoload.php';
if (is_file($componentAutoload)) {
    require_once $componentAutoload;
}

$modx = new \MODX\Revolution\modX();
$modx->initialize('mgr');
$modx->lexicon->load('mxlogger:default');

$pass = 0;
$fail = 0;
function check($cond, $name)
{
    global $pass, $fail;
    if ($cond) {
        $pass++;
        echo "  PASS: $name\n";
    } else {
        $fail++;
        echo "  FAIL: $name\n";
    }
}

$Exporter = \MxLogger\Helpers\LogExporter::class;

echo "== decodeJson ==\n";
$pretty = json_encode(['a' => 1, 'b' => 'тест'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
check($Exporter::decodeJson(['a' => 1, 'b' => 'тест']) === $pretty, 'массив -> pretty JSON');
check($Exporter::decodeJson('') === '', 'пустая строка -> пусто');
check($Exporter::decodeJson([]) === '', 'пустой массив -> пусто');
check($Exporter::decodeJson('null') === '', 'строка null -> пусто');
check($Exporter::decodeJson('{"x":1}') === json_encode(['x' => 1], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'JSON-строка -> pretty JSON');
check(strpos($Exporter::decodeJson(['x' => ['y' => 'z']]), 'Array') === false, 'нет литерала "Array"');

echo "== реальные записи ==\n";
$c = $modx->newQuery(\MxLogger\Model\MxLoggerLog::class);
$c->sortby('id', 'DESC');
$c->limit(10);
$objects = $modx->getCollection(\MxLogger\Model\MxLoggerLog::class, $c);
check(count($objects) > 0, 'выборка вернула записи (' . count($objects) . ')');

$exMd = new \MxLogger\Helpers\LogExporter($modx, 'md');
$exTxt = new \MxLogger\Helpers\LogExporter($modx, 'txt');

$sampleMd = '';
$sampleTxt = '';
$traceChecked = false;
$contextChecked = false;

foreach ($objects as $o) {
    $r = $o->toArray();
    $id = (int) $r['id'];
    $md = $exMd->renderRow($r);
    $txt = $exTxt->renderRow($r);
    if ($sampleMd === '') {
        $sampleMd = $md;
        $sampleTxt = $txt;
    }

    check($id > 0, "id > 0 (#$id)");
    check(strpos($md, '#' . $id) !== false, "md содержит реальный id #$id");
    check(strpos($txt, '#' . $id) !== false, "txt содержит реальный id #$id");
    check(strpos($md, 'Array') === false, "md #$id без литерала Array");
    check(strpos($txt, 'Array') === false, "txt #$id без литерала Array");
    if (!empty($r['level'])) {
        check(strpos($md, '[' . strtoupper((string) $r['level']) . ']') !== false, "md #$id содержит уровень " . $r['level']);
    }

    if (!$contextChecked && $Exporter::decodeJson($r['context'] ?? '') !== '') {
        $contextChecked = true;
        check(strpos($md, 'context') !== false && strpos($md, '```json') !== false, "md #$id: context отрендерен JSON-блоком");
    }
    if (!$traceChecked && $Exporter::decodeJson($r['trace'] ?? '') !== '') {
        $traceChecked = true;
        check(strpos($md, 'trace') !== false && strpos($md, '```json') !== false, "md #$id: trace отрендерен JSON-блоком");
    }
}

if (!$traceChecked) {
    echo "  SKIP: ни у одной записи нет trace\n";
}
if (!$contextChecked) {
    echo "  SKIP: ни у одной записи нет context\n";
}

echo "== шапка ==\n";
$hdr = $exMd->renderHeader(count($objects), 'level=error', '2026-06-22 00:00:00');
check(strpos($hdr, 'mxLogger') !== false, 'шапка содержит заголовок');
check(strpos($hdr, 'level=error') !== false, 'шапка содержит описание фильтра');

echo "\n--- ОБРАЗЕЦ (первая запись, md) ---\n";
echo $sampleMd;
echo "--- ОБРАЗЕЦ (первая запись, txt) ---\n";
echo $sampleTxt;

echo "\n=== ИТОГ: $pass passed, $fail failed ===\n";
exit($fail ? 1 : 0);
