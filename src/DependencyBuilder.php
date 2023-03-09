<?php

namespace Prestashop\ModuleLibMboInstaller;

use Prestashop\ModuleLibGuzzleAdapter\Interfaces\ClientExceptionInterface;
use Symfony\Component\Routing\Router;

class DependencyBuilder
{
    const DEPENDENCY_FILENAME = 'ps_dependencies.json';
    const GET_PARAMETER = 'mbo_action_needed';
    const INSTALL_ACTION = 'install';
    const ENABLE_ACTION = 'enable';
    const APP_STATE_LAUNCHABLE = 'launchable';
    const APP_STATE_MBO_IN_PROGRESS = 'mbo_in_progress';
    const APP_STATE_AUTOSTART = 'autostart';

    /**
     * @var \ModuleCore
     */
    protected $module;

    /**
     * @var Router
     */
    protected $router;

    /**
     * @param \ModuleCore $module
     *
     * @throws \Exception
     */
    public function __construct($module)
    {
        $this->module = $module;
        $this->buildRouter();
    }

    /**
     * Handle dependencies behavior and return dependencies data array to be given to the CDC
     *
     * @return array{
     *     "module_display_name": string,
     *     "module_name": string,
     *     "module_version": string,
     *     "ps_version": string,
     *     "php_version": string,
     *     "locale": string,
     *     "app_state": string,
     *     "dependencies": array{}|array{ps_mbo: array<string, bool|string>}
     * }
     *
     * @throws \Exception|ClientExceptionInterface
     */
    public function handleDependencies()
    {
        $appState = $this->handleMboInstallation();

        return $this->buildDependenciesContext($appState);
    }

    /**
     * Install or enable the MBO depending on the action requested
     *
     * @return string
     *
     * @throws \Exception|ClientExceptionInterface
     */
    protected function handleMboInstallation()
    {
        if (!isset($_GET[self::GET_PARAMETER])) {
            return self::APP_STATE_LAUNCHABLE;
        }

        $mboStatus = (new Presenter())->present();
        $installer = new Installer(_PS_VERSION_);

        if ($mboStatus['isInstalled'] && $mboStatus['isEnabled']) {
            return self::APP_STATE_AUTOSTART;
        }

        if (!$mboStatus['isInstalled']) {
            $installer->installModule();
        } elseif (!$mboStatus['isEnabled']) {
            $installer->enableModule();
        }

        // Force another refresh of the page to correctly clear the cache and load MBO configurations
        header('Refresh:0');
        // To avoid wasting time rerendering the entire page, die immediately
        return self::APP_STATE_MBO_IN_PROGRESS;
    }

    /**
     * Build the dependencies data array to be given to the CDC
     *
     * @param string $appState
     *
     * @return array{
     *     "module_display_name": string,
     *     "module_name": string,
     *     "module_version": string,
     *     "ps_version": string,
     *     "php_version": string,
     *     "locale": string,
     *     "app_state": string,
     *     "dependencies": array{}|array{ps_mbo: array<string, bool|string>}
     * }
     *
     * @throws \Exception
     */
    protected function buildDependenciesContext($appState = self::APP_STATE_LAUNCHABLE)
    {
        $data = [
            'module_display_name' => (string) $this->module->displayName,
            'module_name' => (string) $this->module->name,
            'module_version' => (string) $this->module->version,
            'ps_version' => (string) _PS_VERSION_,
            'php_version' => (string) PHP_VERSION,
            'app_state' => $appState,
            'dependencies' => [],
        ];

        $context = \ContextCore::getContext();
        if ($context !== null && $context->employee !== null) {
            $locale = \DbCore::getInstance()->getValue('SELECT `locale` FROM `' . _DB_PREFIX_ . 'lang` WHERE `id_lang`=' . pSQL((string) $context->employee->id_lang));
        }

        if (empty($locale)) {
            $locale = 'en-GB';
        }

        $data['locale'] = (string) $locale;

        $dependencyFile = $this->module->getLocalPath() . self::DEPENDENCY_FILENAME;
        if (!file_exists($dependencyFile)) {
            throw new \Exception(self::DEPENDENCY_FILENAME . ' file is not found in ' . $this->module->getLocalPath());
        }

        if ($fileContent = file_get_contents($dependencyFile)) {
            $dependenciesContent = json_decode($fileContent, true);
        }
        if (!isset($dependenciesContent) || json_last_error() != JSON_ERROR_NONE) {
            throw new \Exception(self::DEPENDENCY_FILENAME . ' file may be malformatted.');
        }

        if (!is_array($dependenciesContent) || empty($dependenciesContent['dependencies']) || !is_array($dependenciesContent['dependencies'])) {
            $mboDependencyData = $this->addMboInDependencies();

            if ($mboDependencyData) {
                $data['dependencies'][Installer::MODULE_NAME] = $mboDependencyData;
            }

            return $data;
        }

        if (!isset($dependenciesContent['dependencies'][Installer::MODULE_NAME])) {
            $dependenciesContent['dependencies'][] = Installer::MODULE_NAME;
        }

        foreach ($dependenciesContent['dependencies'] as $dependencyName) {
            $dependencyData = \DbCore::getInstance()->getRow('SELECT `id_module`, `active`, `version` FROM `' . _DB_PREFIX_ . 'module` WHERE `name` = "' . pSQL((string) $dependencyName) . '"');

            $data['dependencies'][$dependencyName] = $this->buildRoutesForModule($dependencyName);
            if (!$dependencyData) {
                $data['dependencies'][$dependencyName]['installed'] = false;
                continue;
            }
            $data['dependencies'][$dependencyName] = array_merge($data['dependencies'][$dependencyName], [
                'installed' => true,
                'enabled' => isset($dependencyData['active']) && (bool) $dependencyData['active'],
                'current_version' => isset($dependencyData['version']) ? $dependencyData['version'] : null,
            ]);
        }

        return $data;
    }

    /**
     * @param string $moduleName
     *
     * @return array<string, string>
     */
    protected function buildRoutesForModule($moduleName)
    {
        $urls = [];
        foreach (['install', 'enable', 'upgrade'] as $action) {
            $urls[$action] = $this->router->generate('admin_module_manage_action', [
                'action' => $action,
                'module_name' => $moduleName,
            ]);
        }

        return $urls;
    }

    /**
     * @return void
     *
     * @throws \Exception
     */
    protected function buildRouter()
    {
        global $kernel;
        if (!$kernel instanceof \AppKernel) {
            throw new \Exception('Unable to retrieve Symfony AppKernel.');
        }

        $container = $kernel->getContainer();
        if (!$container instanceof \Symfony\Component\DependencyInjection\ContainerInterface) {
            throw new \Exception('Unable to retrieve Symfony container.');
        }

        $router = $container->get('router');
        if (!$router instanceof Router) {
            throw new \Exception('Unable to retrieve Symfony router.');
        }
        $this->router = $router;
    }

    /**
     * @return array<string,bool|string>|null
     */
    protected function addMboInDependencies()
    {
        $mboStatus = (new Presenter())->present();

        if ((bool) $mboStatus['isEnabled']) {
            return null;
        }

        $mboRoutes = $this->buildRoutesForModule(Installer::MODULE_NAME);

        return array_merge([
            'current_version' => (string) $mboStatus['version'],
            'installed' => (bool) $mboStatus['isInstalled'],
            'enabled' => false,
        ], $mboRoutes);
    }
}
