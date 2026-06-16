<strong>mxLogger</strong> — удобное логирование процессов в MODX Revolution 3.

Расставьте вызовы логгера с общими тэгами (например «purchase» и «cart») — и в менеджере найдёте все записи процесса по тэгу. В комплекте — готовый плагин, логирующий корзину и оформление заказа miniShop3, и автономный просмотрщик логов в обход MODX.

<strong>Возможности</strong>
<ul>
<li>Логирование с тэгами; у одной записи может быть <em>несколько</em> тэгов.</li>
<li>Группировка одной воронки через идентификатор процесса (process_uid).</li>
<li>Автозахват источника вызова: класс, метод, файл, строка (с пропуском диспетчерских кадров MODX 3 — источник указывает на реальный код).</li>
<li>Для уровней «warning» и «error» — стэк вызовов и параметры (объекты сворачиваются в имя класса).</li>
<li>Автоматически пишутся пользователь, сессия, ip, время.</li>
<li>Whitelist-фильтры записи: по пользователю, группе, сессии, куке.</li>
<li>Менеджерный грид (Vue 3) с фильтрами, окном детали и кликом по значению для фильтрации.</li>
<li>Просмотр логов в обход MODX — CLI и WEB (standalone.php).</li>
</ul>

<strong>Требования к окружению</strong>
<ul>
<li>MODX Revolution 3.x.</li>
<li>PHP 8.1 или новее.</li>
<li>MySQL 5.6+ / MariaDB на InnoDB — нужен FULLTEXT-индекс по тэгам; таблица в utf8mb4.</li>
<li>Расширения PHP: PDO, mbstring, json.</li>
<li>Плагин логирования корзины/заказа работает только при установленном miniShop3.</li>
</ul>

<strong>Установка</strong>
<ul>
<li>Установите пакет через «Управление пакетами».</li>
<li>Создаются автоматически: таблица логов (utf8mb4), namespace, меню «Компоненты → mxLogger», системные настройки, сниппет mxLogger и плагины (ротация, логирование miniShop3).</li>
</ul>

<strong>Как добавлять логирование в свой код</strong>
В MODX 3 сервис берётся из контейнера: <code>$mxl = $modx->services->get('mxlogger');</code>
<code>$mxl->debug('purchase', 'Открыта корзина');
$mxl->info('purchase', 'Корзина создана', ['cart_id' => $id]);
$mxl->warning('purchase', 'Низкий остаток', ['left' => 2]);
$mxl->error('purchase', 'Платёж отклонён', ['code' => 'declined']);</code>

Несколько тэгов на одну запись:
<code>$mxl->info(['cart', 'purchase'], 'Товар добавлен', ['product' => $pid]);</code>

Процесс — один экземпляр воронки = один общий идентификатор:
<code>$p = $mxl->process(['cart', 'purchase']); // идентификатор сгенерируется автоматически
$p->info('Старт оплаты', ['order' => 42]);
$p->error('Платёж отклонён', ['code' => 'declined']);
$uid = $p->getUid();                       // можно сохранить и продолжить позже</code>

Принудительно снять полный стэк и параметры для конкретной записи:
<code>$mxl->info('purchase', 'Создан заказ', $ctx, ['trace' => true]);</code>

Если логируете через свою обёртку/фасад над сервисом — укажите её класс в опции skip_classes, чтобы «Источник» указывал на реальный вызывающий код, а не на обёртку. Можно точное имя класса или префикс пространства имён со «\» на конце:
<code>$mxl->info('payment', 'Платёж создан', $ctx, ['skip_classes' => ['My\\Payment\\Logger']]);</code>

Из чанка или Fenom:
<code>[[!mxLogger? &tags=`cart,purchase` &level=`info` &message=`Товар добавлен`]]</code>

<blockquote>Тэги — только латиница и цифры в нижнем регистре, без пробелов; лишние символы вырезаются. Уровни: «debug», «info», «warning», «error».</blockquote>

<strong>Просмотр и настройки</strong>
<ul>
<li>Логи — в менеджере: «Компоненты → mxLogger».</li>
<li>Глобально: <code>mxlogger.enabled</code>, минимальный уровень <code>mxlogger.min_level</code>.</li>
<li>Захват трассировки <code>mxlogger.capture_mode</code> (off/caller/full/auto).</li>
<li>Ротация: <code>mxlogger.log_lifetime</code> (сек, 0 — не удалять), <code>mxlogger.rotate_interval</code>.</li>
<li>На случай, если MODX не грузится — <code>assets/components/mxlogger/standalone.php</code> (CLI всегда; web по ключу <code>core/components/mxlogger/standalone.key</code>).</li>
</ul>
