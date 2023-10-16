<?php

namespace Prestashop\ModuleLibMboInstaller;

use Symfony\Component\Routing\Router;

class DependencyBuilder
{
    const DEPENDENCY_FILENAME = 'module_dependencies.json';
    const GET_PARAMETER = 'mbo_action_needed';

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
     *     "dependencies": array{}|array{ps_mbo: array<string, bool|string|int>}
     * }
     *
     * @throws \Exception
     */
    public function handleDependencies()
    {
        $this->handleMboInstallation();

        return $this->buildDependenciesContext();
    }

    /**
     * Install or enable the MBO depending on the action requested
     *
     * @return void
     */
    protected function handleMboInstallation()
    {
        if (!isset($_GET[self::GET_PARAMETER]) || !$this->isMboNeeded()) {
            return;
        }

        $mboStatus = (new Presenter())->present();
        $installer = new Installer(_PS_VERSION_);

        if ($mboStatus['isInstalled'] && $mboStatus['isEnabled']) {
            return;
        }

        $data = [Installer::MODULE_NAME => [
            'status' => true,
        ]];

        try {
            if (!$mboStatus['isInstalled']) {
                $installer->installModule();
            } elseif (!$mboStatus['isEnabled']) {
                $installer->enableModule();
            }
        } catch (\Exception $e) {
            $data[Installer::MODULE_NAME] = [
                'status' => false,
                'msg' => $e->getMessage(),
            ];
        }

        // This call is done in ajax by the CDC, bypass the normal return
        header('Content-type: application/json');
        echo json_encode($data);
        exit;
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
     *     "dependencies": array{}|array{ps_mbo: array<string, bool|string|int>}
     * }
     *
     * @throws \Exception
     */
    protected function buildDependenciesContext()
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
            $mboDependencyData = $this->addMboInDependencies();

            if ($mboDependencyData) {
                $data['dependencies'][Installer::MODULE_NAME] = $mboDependencyData;
            }

            return $data;
        }

        if ($this->isMboNeeded() && !isset($dependenciesContent['dependencies'][Installer::MODULE_NAME])) {
            $dependenciesContent['dependencies'][] = [
                'name' => Installer::MODULE_NAME,
            ];
        }

        foreach ($dependenciesContent['dependencies'] as $dependency) {
            $dependencyData = \DbCore::getInstance()->getRow('SELECT `id_module`, `active`, `version` FROM `' . _DB_PREFIX_ . 'module` WHERE `name` = "' . pSQL((string) $dependency['name']) . '"');

            $data['dependencies'][$dependency['name']] = array_merge($dependency, $this->buildRoutesForModule($dependency['name']));
            if (!$dependencyData) {
                $data['dependencies'][$dependency['name']]['installed'] = false;
                continue;
            }
            $data['dependencies'][$dependency['name']] = array_merge($data['dependencies'][$dependency['name']], [
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
     * @return array<string,bool|string|int>|null
     */
    protected function addMboInDependencies()
    {
        if (!$this->isMboNeeded()) {
            return null;
        }

        $mboStatus = (new Presenter())->present();

        if ((bool) $mboStatus['isEnabled']) {
            return null;
        }

        $mboRoutes = $this->buildRoutesForModule(Installer::MODULE_NAME);

        return array_merge([
            'current_version' => (string) $mboStatus['version'],
            'installed' => (bool) $mboStatus['isInstalled'],
            'enabled' => false,
            'name' => Installer::MODULE_NAME,
        ], $mboRoutes);
    }

    /**
     * @return bool
     */
    protected function isMboNeeded()
    {
        return version_compare(_PS_VERSION_, '1.7.5', '>=');
    }
}
