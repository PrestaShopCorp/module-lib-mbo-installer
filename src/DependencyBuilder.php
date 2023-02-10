<?php

namespace Prestashop\ModuleLibMboInstaller;

use Symfony\Component\Routing\Router;

class DependencyBuilder
{
    const DEPENDENCY_FILENAME = 'ps_dependencies.json';

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
     * Build the dependencies data array to be given to the CDC
     *
     * @return array{
     *     "module_display_name": string,
     *     "module_name": string,
     *     "module_version": string,
     *     "ps_version": string,
     *     "php_version": string,
     *     "locale": string,
     *     "dependencies": array<array{
     *          "min_version"?: string,
     *          "current_version"?: string,
     *          "installed"?: bool,
     *          "enabled"?: bool
     *      }>
     * }
     *
     * @throws \Exception
     */
    public function buildDependencies()
    {
        $data = [
            'module_display_name' => (string) $this->module->displayName,
            'module_name' => (string) $this->module->name,
            'module_version' => (string) $this->module->version,
            'ps_version' => (string) _PS_VERSION_,
            'php_version' => (string) PHP_VERSION,
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
            return $data;
        }

        foreach ($dependenciesContent['dependencies'] as $dependencyName => $dependencyMinVersion) {
            $dependencyData = \DbCore::getInstance()->getRow('SELECT `id_module`, `active`, `version` FROM `' . _DB_PREFIX_ . 'module` WHERE `name` = "' . pSQL((string) $dependencyName) . '"');

            $data['dependencies'][$dependencyName] = array_merge(['min_version' => (string) $dependencyMinVersion], $this->buildRoutesForModule($dependencyName));
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
            throw new \Exception('Unable to retrieve Symfony ContainerInterface.');
        }

        $router = $container->get('router');
        if (!$router instanceof Router) {
            throw new \Exception('Unable to retrieve Symfony Router.');
        }
        $this->router = $router;
    }
}
