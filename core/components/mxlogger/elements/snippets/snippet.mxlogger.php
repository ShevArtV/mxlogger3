<?php
/**
 * Сниппет mxLogger — запись лога из чанка/шаблона/Fenom (MODX 3).
 *
 * Пример:
 *   [[!mxLogger? &tags=`purchase` &level=`info` &message=`Открыта страница оплаты`]]
 *   {'!mxLogger' | snippet : ['tags' => 'cart,purchase', 'level' => 'error', 'message' => 'Ошибка']}
 *
 * Параметры: &tags (синоним &tag), &level (debug|info|warning|error), &message,
 *   &process_uid, &context (JSON-строка), &trace (1|full|caller).
 *
 * @var \MODX\Revolution\modX $modx
 * @var array $scriptProperties
 */

/** @var \MxLogger\MxLogger $mxlogger */
$mxlogger = $modx->services->has('mxlogger') ? $modx->services->get('mxlogger') : null;
if (!($mxlogger instanceof \MxLogger\MxLogger)) {
    return '';
}

$tags = $modx->getOption('tags', $scriptProperties, $modx->getOption('tag', $scriptProperties, ''));
if ($tags === '' || $tags === null) {
    return '';
}

$level = $modx->getOption('level', $scriptProperties, 'info');
$message = $modx->getOption('message', $scriptProperties, '');

$context = $modx->getOption('context', $scriptProperties, '');
if (is_string($context) && $context !== '') {
    $decoded = json_decode($context, true);
    $context = is_array($decoded) ? $decoded : ['raw' => $context];
} elseif (!is_array($context)) {
    $context = [];
}

$options = [];
if (($uid = $modx->getOption('process_uid', $scriptProperties, '')) !== '') {
    $options['process_uid'] = $uid;
}
if (($trace = $modx->getOption('trace', $scriptProperties, '')) !== '') {
    $options['trace'] = ($trace === '1' || $trace === 1) ? true : $trace;
}
// Сниппет добавляет свой кадр — пропускаем его при разборе backtrace.
$options['skip'] = 1;

$mxlogger->log($tags, $level, $message, $context, $options);

return '';
