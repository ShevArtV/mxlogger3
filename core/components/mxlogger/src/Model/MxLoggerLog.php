<?php

declare(strict_types=1);

namespace MxLogger\Model;

use xPDO\Om\xPDOSimpleObject;

/**
 * Одна запись лога mxLogger.
 *
 * @property string      $tags        CSV-обёртка тэгов: ,cart,purchase,
 * @property string|null $process_uid Идентификатор экземпляра воронки
 * @property string      $level       debug|info|warning|error
 * @property string|null $message     Текст сообщения
 * @property array|null  $context     Произвольные данные (JSON)
 * @property string|null $class       Класс-источник вызова
 * @property string|null $function    Метод/функция-источник
 * @property string|null $file        Файл-источник
 * @property int|null    $line        Строка-источник
 * @property array|null  $trace       Трассировка {stack, params} (JSON)
 * @property int         $user_id     ID пользователя
 * @property string|null $session_id  Идентификатор сессии
 * @property string|null $ip          IP-адрес
 * @property int         $createdon   Unix-время записи
 */
class MxLoggerLog extends xPDOSimpleObject
{
}
