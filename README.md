# mxlogger3

Логирование процессов с тэгами для **MODX Revolution 3**. Порт компонента mxLogger
с MODX 2.

Компонент даёт:
- сервис логирования с уровнями (`debug/info/warning/error`) и скоупом процесса
- мультитэги (FULLTEXT-поиск, фильтр any/all), автозахват «Источника» и стэка
- менеджерный грид логов (Vue 3) с фильтрами, окном детали и очисткой
- сниппет для логирования из чанков/Fenom
- ротацию старых записей, события до/после записи
- логирование событий miniShop3 (корзина/заказ) готовым плагином
- `standalone.php` — просмотр логов в обход MODX (на случай, если приложение не грузится)

## Стек

- PHP 8.1+
- MODX Revolution 3 (xPDO 3)
- Vue 3 + PrimeVue 4 + Vite для manager UI
- `package_builder` / `modxapp` для сборки transport package
- miniShop3 — опционально (для плагина логирования магазина)

## Доступ к сервису

В MODX 3 сервис берётся из DI-контейнера (не `getService`/`extension_packages`):

```php
$mxl = $modx->services->get('mxlogger');
$mxl->info('purchase', 'Корзина создана', ['cart_id' => $id]);

$p = $mxl->process(['cart', 'purchase']);   // воронка с общим process_uid
$p->info('Старт оплаты', ['order' => 42]);
$p->error('Платёж отклонён', ['code' => 'declined']);
```

Сигнатура: `log($tags, $level, $message, array $context = [], array $options = [])`
+ шорткаты `debug/info/warning/error`. Опции: `process_uid`, `trace`
(`caller|full|off`), `skip`, `skip_classes`.

Просмотр логов: менеджер → **Extras → mxLogger**.

## Структура репозитория

- `core/components/mxlogger/` — PHP-исходники (`src/` PSR-4: сервис, модель,
  процессоры; `controllers/` — контроллер страницы; `elements/` — сниппет/плагины;
  `schema/`, `lexicon/`, `bootstrap.php`, runtime `vendor/`)
- `assets/components/mxlogger/` — коннектор, `standalone.php`, исходники и сборка
  manager UI (`js/mgr/` — Vue, `js/mgr/vue-dist/` — собранный бандл)
- `package_builder/packages/mxlogger/` — конфиг сборки, элементы, резолверы
- `_modx2/` — первоисточник версии под MODX 2 (для сверки; в пакет не идёт)

## Правила репозитория

- `package_builder/`, `core/components/mxlogger/vendor/` и `vue-dist/` — в git
  (нужны для рантайма/сборки и попадают в устанавливаемый пакет)
- `node_modules/`, IDE-файлы, кэши, собранные `core/packages/*.zip` — не храним

## Сборка manager UI

```bash
cd assets/components/mxlogger
npm install
npm run build          # → js/mgr/vue-dist/{logs.min.js, logs.min.css}
```

## Сборка пакета

```bash
cd /home/shevartv/projects/apps/mxlogger3
modxapp build mxlogger          # → core/packages/mxlogger-*.transport.zip
```

## Установка / выкатка

Собрать пакет локально → залить zip на сервер → установить (Package Management в
менеджере или transport API). Для чистой переустановки на dev-стенде: снять старый
пакет (`uninstall`+`remove`), снести `core/components/mxlogger` и
`assets/components/mxlogger`, поставить заново из zip, сбросить кэш (`core/cache`).

## Системные настройки (основные)

- `mxlogger.enabled` — глобальный выключатель записи
- `mxlogger.min_level` — минимальный уровень (debug/info/warning/error)
- `mxlogger.capture_mode` — off/caller/full/auto (auto: caller, для warning/error — full)
- `mxlogger.tag_filter_mode` — auto/fulltext/like
- `mxlogger.log_lifetime`, `mxlogger.rotate_interval` — ротация
- `mxlogger.filter_user|filter_usergroup|filter_session|filter_cookie` —
  whitelist-фильтры записи (для отладки на проде без флуда)
