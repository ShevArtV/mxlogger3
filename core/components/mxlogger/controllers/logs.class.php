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
        $bundle = MODX_ASSETS_PATH . 'components/mxlogger/js/mgr/vue-dist/logs.min.js';
        $ver = @filemtime($bundle) ?: $this->modx->getOption('mxlogger.version', null, '1.0.0');
        $this->modx->regClientStartupHTMLBlock(
            '<script type="module" src="' . $assetsUrl . 'js/mgr/vue-dist/logs.min.js?v=' . rawurlencode((string) $ver) . '"></script>'
        );
    }

    public function getTemplateFile()
    {
        return MODX_CORE_PATH . 'components/mxlogger/templates/mgr/logs.tpl';
    }
}
