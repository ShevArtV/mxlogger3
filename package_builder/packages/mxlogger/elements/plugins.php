<?php

// Все привязки плагинов логгера — priority -10: логгер идёт первым в цепочке события.
// modX::invokeEvent() сбрасывает returnedValues перед каждым плагином, и логгер в конце
// цепочки затирал данные плагинов сайта (msOnSubmitOrder).
return [
    'mxLoggerRotate' => [
        'description' => 'Ротация (автоудаление) старых записей лога mxLogger по mxlogger.log_lifetime.',
        'content' => 'file:elements/plugins/plugin.mxloggerrotate.php',
        'events' => [
            'OnMODXInit' => ['priority' => -10],
        ],
    ],
    'mxLoggerMiniShop3' => [
        'description' => 'Логирование событий miniShop3 (корзина/заказ) в mxLogger. Требует miniShop3.',
        'content' => 'file:elements/plugins/plugin.mslogger.php',
        'events' => [
            'msOnAddToCart' => ['priority' => -10],
            'msOnChangeInCart' => ['priority' => -10],
            'msOnRemoveFromCart' => ['priority' => -10],
            'msOnEmptyCart' => ['priority' => -10],
            'msOnAddToOrder' => ['priority' => -10],
            'msOnRemoveFromOrder' => ['priority' => -10],
            'msOnBeforeCreateOrder' => ['priority' => -10],
            'msOnCreateOrder' => ['priority' => -10],
            'msOnSubmitOrder' => ['priority' => -10],
            'msOnChangeOrderStatus' => ['priority' => -10],
        ],
    ],
];
