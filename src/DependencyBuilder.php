<?php

namespace Prestashop\ModuleLibMboInstaller;

use Psr\Http\Client\ClientExceptionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Router;

class DependencyBuilder
{
    const DEPENDENCY_FILENAME = 'module_dependencies.json';
    const GET_PARAMETER = 'mbo_action_needed';
    const DEFAULT_LOCALE = 'en-GB';
    const DEFAULT_HELP_URLS = [
        'en_GB' => 'https://addons.prestashop.com/en/contact-us',
        'en_US' => 'https://addons.prestashop.com/en/contact-us',
        'de_DE' => 'https://addons.prestashop.com/de/contact-us',
        'es_ES' => 'https://addons.prestashop.com/es/contacte-con-nosotros',
        'fr_FR' => 'https://addons.prestashop.com/fr/contactez-nous',
        'it_IT' => 'https://addons.prestashop.com/it/contact-us',
        'nl_NL' => 'https://addons.prestashop.com/nl/contact-us',
        'pl_PL' => 'https://addons.prestashop.com/pl/contact-us',
        'pt_PT' => 'https://addons.prestashop.com/pt/contact-us',
        'ro_RO' => 'https://addons.prestashop.com/en/contact-us',
        'ru_RU' => 'https://addons.prestashop.com/ru/contact-us',
    ];

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
     * @throws ClientExceptionInterface
     */
    public function handleDependencies()
    {
        $this->handleMboInstallation();

        return $this->buildDependenciesContext();
    }

    /**
     * @return bool
     *
     * @throws \Exception
     */
    public function areDependenciesMet()
    {
        $dependencies = $this->getDependencies(false);

        foreach ($dependencies as $dependencyName => $dependency) {
            if (
                !array_key_exists('installed', $dependency)
                || !array_key_exists('enabled', $dependency)
                || false === $dependency['installed']
                || false === $dependency['enabled']
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Install or enable the MBO depending on the action requested
     *
     * @return void
     *
     * @throws \Exception|ClientExceptionInterface
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
        $helpUrlTranslations = $this->getHelpUrlSpecification();
        $currentLocale = $this->getLocale();
        $translationsLocale = str_replace('-', '_', $currentLocale);

        if (!array_key_exists($translationsLocale, $helpUrlTranslations)) {
            $translationsLocale = str_replace('-', '_', self::DEFAULT_LOCALE);
        }

        return [
            'module_display_name' => (string) $this->module->displayName,
            'module_name' => (string) $this->module->name,
            'module_version' => (string) $this->module->version,
            'ps_version' => (string) _PS_VERSION_,
            'php_version' => (string) PHP_VERSION,
            'locale' => $currentLocale,
            'help_url' => $helpUrlTranslations[$translationsLocale],
            'dependencies' => $this->getDependencies(true),
        ];
    }

    /**
     * @return string
     */
    private function getLocale()
    {
        $context = \ContextCore::getContext();
        if ($context !== null && $context->employee !== null) {
            $locale = \DbCore::getInstance()->getValue(
                sprintf(
                    'SELECT `locale` FROM `%slang` WHERE `id_lang`=%s',
                    _DB_PREFIX_,
                    pSQL((string) $context->employee->id_lang)
                )
            );
        }

        if (empty($locale)) {
            return self::DEFAULT_LOCALE;
        }

        return $locale;
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
            if ($action === 'install' && $moduleName === 'ps_mbo' && !empty($_SERVER['REQUEST_URI'])) {
                $query = http_build_query(array_merge($_GET, [self::GET_PARAMETER => '1']));
                $baseUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
                $route = $baseUri . '?' . $query;
            } else {
                $route = $this->router->generate('admin_module_manage_action', [
                    'action' => $action,
                    'module_name' => $moduleName,
                ]);
            }

            if (is_string($route)) {
                $urls[$action] = $route;
            }
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
        if (!$container instanceof ContainerInterface) {
            throw new \Exception('Unable to retrieve Symfony container.');
        }

        $router = $container->get('router');
        if (!$router instanceof Router) {
            throw new \Exception('Unable to retrieve Symfony router.');
        }
        $this->router = $router;
    }

    /**
     * @param bool $addRoutes
     *
     * @return array{
     *   "name": string,
     *   "installed": bool,
     *   "enabled": bool,
     *   "current_version": string,
     * }|non-empty-array<string, bool|string>|null
     *
     * @throws \Exception
     */
    protected function addMboInDependencies($addRoutes = false)
    {
        if (!$this->isMboNeeded()) {
            return null;
        }

        $mboStatus = (new Presenter())->present();

        $specification = [
            'current_version' => (string) $mboStatus['version'],
            'installed' => (bool) $mboStatus['isInstalled'],
            'enabled' => (bool) $mboStatus['isEnabled'],
            'name' => Installer::MODULE_NAME,
        ];

        if (!$addRoutes) {
            return $specification;
        }

        return array_merge(
            $specification,
            $this->buildRoutesForModule(Installer::MODULE_NAME)
        );
    }

    /**
     * @return bool
     */
    protected function isMboNeeded()
    {
        return version_compare(_PS_VERSION_, '1.7.5', '>=');
    }

    /**
     * @param bool $addRoutes
     *
     * @return array<string, array<string, mixed>>
     *
     * @throws \Exception
     */
    private function getDependencies($addRoutes = false)
    {
        $dependenciesContent = $this->getDependenciesSpecification();

        if (empty($dependenciesContent)) {
            $mboDependency = $this->addMboInDependencies($addRoutes);
            if (null === $mboDependency) {
                return [];
            }

            return [
                Installer::MODULE_NAME => $mboDependency,
            ];
        }

        if ($this->isMboNeeded() && !isset($dependenciesContent[Installer::MODULE_NAME])) {
            $dependenciesContent[] = [
                'name' => Installer::MODULE_NAME,
            ];
        }

        $dependencies = [];
        foreach ($dependenciesContent as $dependency) {
            if (
                !is_array($dependency)
                || !array_key_exists('name', $dependency)
                || !is_string($dependency['name'])
            ) {
                continue;
            }

            $dependencyData = \DbCore::getInstance()->getRow(
                sprintf(
                    'SELECT `id_module`, `active`, `version` FROM `%smodule` WHERE `name` = "%s"',
                    _DB_PREFIX_,
                    pSQL((string) $dependency['name'])
                )
            );

            if ($dependencyData && is_array($dependencyData) && !empty($dependencyData['id_module'])) {
                // For PS < 8.0, enable/disable for a module is decided by the shop association
                // We assume that if the module is disabled for one shop, i's considered as disabled
                $isModuleActiveForAllShops = (bool) \DbCore::getInstance()->getValue(
                    sprintf("SELECT id_module
                            FROM `%smodule_shop`
                            WHERE id_module=%d AND id_shop IN ('%s')
                            GROUP BY id_module
                            HAVING COUNT(*)=%d",
                        _DB_PREFIX_,
                        (int) $dependencyData['id_module'],
                        implode(',', array_map('intval', \Shop::getContextListShopID())),
                        (int) count(\Shop::getContextListShopID())
                    )
                );

                $dependencyData['active'] = $isModuleActiveForAllShops;
            }

            if ($addRoutes) {
                $dependencies[$dependency['name']] = array_merge(
                    $dependency,
                    $this->buildRoutesForModule($dependency['name'])
                );
            } else {
                $dependencies[$dependency['name']] = $dependency;
            }

            if (!$dependencyData) {
                $dependencies[$dependency['name']]['installed'] = false;
                $dependencies[$dependency['name']]['enabled'] = false;
                continue;
            }
            $dependencies[$dependency['name']] = array_merge($dependencies[$dependency['name']], [
                'installed' => true,
                'enabled' => isset($dependencyData['active']) && (bool) $dependencyData['active'],
                'current_version' => isset($dependencyData['version']) ? $dependencyData['version'] : null,
            ]);
        }

        return $dependencies;
    }

    /**
     * @return array{
     *     "dependencies": array<string, string|int|bool>
     * }
     *
     * @throws \Exception
     */
    private function getDependenciesSpecification()
    {
        $configFileContent = $this->getConfigFileContent();

        if (
            !isset($configFileContent)
            || !is_array($configFileContent)
            || !array_key_exists('dependencies', $configFileContent)
            || json_last_error() != JSON_ERROR_NONE
        ) {
            throw new \Exception(self::DEPENDENCY_FILENAME . ' file may be malformed.');
        }

        return $configFileContent['dependencies'];
    }

    /**
     * @return array<string, string>
     */
    private function getHelpUrlSpecification()
    {
        $configFileContent = $this->getConfigFileContent();
        $defaultLocaleFormatted = str_replace('-', '_', self::DEFAULT_LOCALE);

        if (
            !isset($configFileContent)
            || !is_array($configFileContent)
            || !array_key_exists('help_url', $configFileContent)
            || json_last_error() != JSON_ERROR_NONE
        ) {
            return self::DEFAULT_HELP_URLS;
        }

        $helpUrlSpecification = $configFileContent['help_url'];

        // Validate help_url specification
        if (!array_key_exists('default', $helpUrlSpecification)) {
            return self::DEFAULT_HELP_URLS;
        }

        // Transform keys to use CLDR notation (en_US) instead of IETF (en-US)
        // @see PrestaShop\PrestaShop\Core\Localization\CLDR\Reader
        $helpUrlSpecificationFormatted = [];
        array_walk(
            $helpUrlSpecification,
            function (string $value, string $key) use (&$helpUrlSpecificationFormatted) {
                $helpUrlSpecificationFormatted[str_replace('-', '_', $key)] = $value;
            }
        );

        $helpUrlSpecification = $helpUrlSpecificationFormatted;

        // Set English as default language
        if (!array_key_exists($defaultLocaleFormatted, $helpUrlSpecification)) {
            $helpUrlSpecification[$defaultLocaleFormatted] = $helpUrlSpecification['default'];
        }
        unset($helpUrlSpecification['default']);

        return $helpUrlSpecification;
    }

    /**
     * @return mixed|null
     *
     * @throws \Exception
     */
    private function getConfigFileContent()
    {
        $dependencyFile = $this->module->getLocalPath() . self::DEPENDENCY_FILENAME;
        if (!file_exists($dependencyFile)) {
            throw new \Exception(self::DEPENDENCY_FILENAME . ' file is not found in ' . $this->module->getLocalPath());
        }

        if ($fileContent = file_get_contents($dependencyFile)) {
            return json_decode($fileContent, true);
        }

        return null;
    }
}
