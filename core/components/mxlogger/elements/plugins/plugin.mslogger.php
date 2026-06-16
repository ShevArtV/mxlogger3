<?php

/**
 * Плагин mxLoggerMiniShop3 — логирует действия с корзиной и оформление заказа miniShop3.
 *
 * Тэги: cart (корзина), order (заказ), purchase (сквозной для всей покупки).
 * Все события одной сессии объединяются общим process_uid.
 *
 * ВАЖНО: ms3 (Utils::invokeEvent) трактует НЕпустой возврат плагина как ошибку и
 * прерывает действие. Поэтому плагин всегда возвращает пусто (return;).
 *
 * @var \MODX\Revolution\modX $modx
 * @var array $scriptProperties
 */

/** @var \MxLogger\MxLogger $mxl */
$mxl = $modx->services->has('mxlogger') ? $modx->services->get('mxlogger') : null;
if (!($mxl instanceof \MxLogger\MxLogger)) {
    return;
}

$eventName = $modx->event->name;
$params = ($modx->event && is_array($modx->event->params)) ? $modx->event->params : (array) ($scriptProperties ?? []);

$CART = ['cart', 'purchase'];
$ORDER = ['order', 'purchase'];

$tags = null;
$level = 'info';
$message = '';
$context = [];

/** @var \MiniShop3\Model\msOrder|null $order Объект заказа из события (если есть). */
$order = $params['msOrder'] ?? null;
$orderInfo = static function ($order): array {
    if (!is_object($order) || !method_exists($order, 'get')) {
        return [];
    }
    return [
        'order_id' => $order->get('id'),
        'num'      => $order->get('num'),
        'cost'     => $order->get('cost'),
    ];
};

switch ($eventName) {

    /* ---------- Корзина ---------- */
    case 'msOnAddToCart':
        $tags = $CART;
        $message = 'Товар добавлен в корзину';
        $context = ['product_key' => $params['product_key'] ?? null, 'count' => $params['count'] ?? null];
        break;

    case 'msOnChangeInCart':
        $tags = $CART;
        $message = 'Изменено количество в корзине';
        $context = ['product_key' => $params['product_key'] ?? null, 'count' => $params['count'] ?? null];
        break;

    case 'msOnRemoveFromCart':
        $tags = $CART;
        $message = 'Товар удалён из корзины';
        $context = ['product_key' => $params['product_key'] ?? null];
        break;

    case 'msOnEmptyCart':
        $tags = $CART;
        $message = 'Корзина очищена';
        break;

    /* ---------- Заказ ---------- */
    case 'msOnAddToOrder':
        $tags = $ORDER;
        $message = 'Поле заказа заполнено';
        $context = ['field' => $params['key'] ?? null];
        break;

    case 'msOnRemoveFromOrder':
        $tags = $ORDER;
        $message = 'Поле заказа удалено';
        $context = ['field' => $params['key'] ?? null];
        break;

    case 'msOnBeforeCreateOrder':
        $tags = $ORDER;
        $level = 'debug';
        $message = 'Создание заказа (до)';
        $context = $orderInfo($order);
        break;

    case 'msOnCreateOrder':
        $tags = $ORDER;
        $message = 'Заказ создан';
        $context = $orderInfo($order);
        break;

    case 'msOnSubmitOrder':
        $tags = $ORDER;
        $message = 'Оформление заказа (submit)';
        $data = $params['data'] ?? null;
        $context = ['fields' => is_array($data) ? array_keys($data) : []];
        break;

    case 'msOnChangeOrderStatus':
        $tags = $ORDER;
        $message = 'Статус заказа изменён';
        $statusName = null;
        $status = $params['status'] ?? null;
        if ($status !== null && class_exists(\MiniShop3\Model\msOrderStatus::class)) {
            if ($st = $modx->getObject(\MiniShop3\Model\msOrderStatus::class, $status)) {
                $statusName = $st->get('name');
            }
        }
        $context = array_merge($orderInfo($order), [
            'old_status'  => $params['old_status'] ?? null,
            'status'      => $status,
            'status_name' => $statusName,
        ]);
        break;

    default:
        return; // событие, на которое плагин не настроен
}

// Сырые параметры события (объекты сворачиваются в object(Класс), с лимитами).
$eventParams = $params;
unset($eventParams['msOrder'], $eventParams['service'], $eventParams['handler'], $eventParams['draft']);
if (!empty($eventParams)) {
    $context['event_params'] = $mxl->sanitizeArgs($eventParams);
}
$context['event'] = $eventName;

// Воронка покупки — общий process_uid по сессии.
// trace=caller: пишем только «Источник», но НЕ аргументы метода ms3 — иначе при
// capture_mode=full в лог попадают полный массив заказа с ПДн (имя/телефон/email/
// адрес) и токен. Полезная сводка уже в context (скаляры order_id/num/cost).
$options = ['trace' => 'caller'];
$sid = session_id();
if ($sid) {
    $options['process_uid'] = 'ms_' . substr(md5($sid), 0, 12);
}

$mxl->log($tags, $level, $message, $context, $options);

return;
