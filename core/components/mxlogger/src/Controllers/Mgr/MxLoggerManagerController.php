<?php

declare(strict_types=1);

namespace MxLogger\Controllers\Mgr;

use MODX\Revolution\modExtraManagerController;

/**
 * Базовый контроллер CMP mxLogger: проброс конфига в JS и общие хелперы.
 */
abstract class MxLoggerManagerController extends modExtraManagerController
{
    protected string $assetsBaseUrl = '';

    public function initialize()
    {
        $this->assetsBaseUrl = MODX_ASSETS_URL . 'components/mxlogger/';
        parent::initialize();
    }

    public function getLanguageTopics()
    {
        return ['mxlogger:default'];
    }

    public function checkPermissions()
    {
        return true;
    }

    /** Зарегистрировать конфиг как глобальную JS-переменную (до загрузки бандла). */
    protected function registerConfig(string $varName, array $config): void
    {
        $json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->modx->regClientStartupHTMLBlock("<script>window.{$varName} = {$json};</script>");
    }

    /** Базовый конфиг для Vue-приложения: коннектор, токен, пути, лексикон. */
    protected function getInitialConfig(): array
    {
        return [
            'connector_url' => $this->assetsBaseUrl . 'connector.php',
            'token' => $this->modx->user
                ? $this->modx->user->getUserToken($this->modx->context->get('key'))
                : '',
            'assets_url' => $this->assetsBaseUrl,
            'lexicon_topics' => $this->getLanguageTopics(),
        ];
    }
}
