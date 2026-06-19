<?php

use MODX\Revolution\modExtraManagerController;

/**
 * CMP mxLogger — журнал логов (Vue-грид).
 *
 * Имя класса плоское и в глобальном namespace — так его строит и ищет
 * MODX\Revolution\modManagerResponse::getControllerClassName() для пары
 * namespace=mxlogger + action=logs (ucfirst(namespace) . action . 'ManagerController'),
 * подгружая файл core/components/mxlogger/controllers/logs.class.php.
 * PSR-4 src/Controllers тут не резолвится — поэтому контроллер страницы здесь.
 */
class MxloggerlogsManagerController extends modExtraManagerController
{
    public function getLanguageTopics()
    {
        return ['mxlogger:default'];
    }

    public function checkPermissions()
    {
        return true;
    }

    public function getPageTitle()
    {
        return $this->modx->lexicon('mxlogger');
    }

    public function loadCustomCssJs()
    {
        $assetsUrl = MODX_ASSETS_URL . 'components/mxlogger/';

        $config = [
            'connector_url' => $assetsUrl . 'connector.php',
            'token' => $this->modx->user
                ? $this->modx->user->getUserToken($this->modx->context->get('key'))
                : '',
            'assets_url' => $assetsUrl,
            'lexicon_topics' => $this->getLanguageTopics(),
        ];
        $json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->modx->regClientStartupHTMLBlock("<script>window.MxLoggerConfig = {$json};</script>");

        // Cache-bust по mtime бандла — каждая пересборка автоматически сбрасывает кэш.
        $distPath = MODX_ASSETS_PATH . 'components/mxlogger/js/mgr/vue-dist/';
        $distUrl = $assetsUrl . 'js/mgr/vue-dist/';
        $ver = @filemtime($distPath . 'logs.min.js') ?: $this->modx->getOption('mxlogger.version', null, '1.0.0');

        // CSS бандла (иконки PrimeIcons + прочие стили, шрифт инлайн в base64).
        // CSS приложения (Vue/PrimeVue/тема/иконки приходят из пакета VueTools).
        if (is_file($distPath . 'logs.min.css')) {
            $cssVer = @filemtime($distPath . 'logs.min.css') ?: $ver;
            $this->modx->regClientCSS($distUrl . 'logs.min.css?v=' . rawurlencode((string) $cssVer));
        }

        // Entry — ES-модуль; Vue/PrimeVue резолвятся из Import Map VueTools.
        // Проверяем наличие карты: если VueTools не установлен — снимаем модуль
        // и показываем понятное сообщение вместо ошибок «Failed to resolve module».
        $this->registerVueToolsCheck();
        $this->modx->regClientStartupHTMLBlock(
            '<script type="module" data-vue-module src="'
            . $distUrl . 'logs.min.js?v=' . rawurlencode((string) $ver) . '"></script>'
        );
    }

    /**
     * Inline-проверка Import Map пакета VueTools (один раз на страницу).
     * Нет карты с ключом vue → удаляем data-vue-module скрипты и алертим.
     */
    protected function registerVueToolsCheck(): void
    {
        $title = json_encode($this->modx->lexicon('mxlogger_error') ?: 'mxLogger', JSON_UNESCAPED_UNICODE);
        $message = json_encode(
            $this->modx->lexicon('mxlogger_vuetools_required')
                ?: 'Для работы требуется пакет VueTools. Установите его через Менеджер пакетов.',
            JSON_UNESCAPED_UNICODE
        );

        $script = <<<JS
<script>
(function () {
    var map = document.querySelector('script[type="importmap"]');
    var ok = false;
    if (map) {
        try { var j = JSON.parse(map.textContent); ok = !!(j.imports && j.imports.vue); } catch (e) { ok = false; }
    }
    if (!ok) {
        document.querySelectorAll('script[type="module"][data-vue-module]').forEach(function (el) { el.remove(); });
        var alertFn = function () {
            if (typeof MODx !== 'undefined' && MODx.msg) { MODx.msg.alert({$title}, {$message}); }
            else { alert({$message}); }
        };
        if (typeof Ext !== 'undefined') { Ext.onReady(alertFn); }
        else { document.addEventListener('DOMContentLoaded', function () { setTimeout(alertFn, 500); }); }
    }
})();
</script>
JS;
        $this->modx->regClientStartupHTMLBlock($script);
    }

    public function getTemplateFile()
    {
        return MODX_CORE_PATH . 'components/mxlogger/templates/mgr/logs.tpl';
    }
}
