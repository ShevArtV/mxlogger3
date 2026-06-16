<?php

declare(strict_types=1);

namespace MxLogger\Controllers\Mgr;

/**
 * CMP mxLogger — журнал логов (Vue-грид). URL: ?a=logs&namespace=mxlogger
 */
class Logs extends MxLoggerManagerController
{
    public function getPageTitle()
    {
        return $this->modx->lexicon('mxlogger');
    }

    public function loadCustomCssJs()
    {
        $this->registerConfig('MxLoggerConfig', $this->getInitialConfig());

        $dist = $this->assetsBaseUrl . 'js/mgr/vue-dist/';
        // Версия пакета для cache-busting бандла.
        $ver = $this->modx->getOption('mxlogger.version', null, '1.0.0');
        $this->modx->regClientStartupHTMLBlock(
            '<script type="module" src="' . $dist . 'logs.min.js?v=' . rawurlencode($ver) . '"></script>'
        );
    }

    public function getTemplateFile()
    {
        return MODX_CORE_PATH . 'components/mxlogger/templates/mgr/logs.tpl';
    }
}
