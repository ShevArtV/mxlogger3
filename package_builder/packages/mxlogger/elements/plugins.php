<?php

return [
    'mxLoggerRotate' => [
        'description' => 'Ротация (автоудаление) старых записей лога mxLogger по mxlogger.log_lifetime.',
        'content' => 'file:elements/plugins/plugin.mxloggerrotate.php',
        'events' => [
            'OnMODXInit',
        ],
    ],
    'mxLoggerMiniShop3' => [
        'description' => 'Логирование событий miniShop3 (корзина/заказ) в mxLogger. Требует miniShop3.',
        'content' => 'file:elements/plugins/plugin.mslogger.php',
        'events' => [
            'msOnAddToCart',
            'msOnChangeInCart',
            'msOnRemoveFromCart',
            'msOnEmptyCart',
            'msOnAddToOrder',
            'msOnRemoveFromOrder',
            'msOnBeforeCreateOrder',
            'msOnCreateOrder',
            'msOnSubmitOrder',
            'msOnChangeOrderStatus',
        ],
    ],
];
