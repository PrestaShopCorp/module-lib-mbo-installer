<?php

namespace Prestashop\ModuleLibMboInstaller;

class DependencyBuilder
{
    const DEPENDENCY_FILENAME = 'ps_dependencies.json';

    /**
     * @var \ModuleCore
     */
    protected $module;

    /**
     * @param \ModuleCore $module
     */
    public function __construct($module)
    {
        $this->module = $module;
    }

    /**
     * Build the dependencies data array to be given to the CDC
     *
     * @return array
     */
    public function buildDependencies()
    {
        $data = [
            'module_display_name' => $this->module->displayName,
            'module_name' => $this->module->name,
            'module_version' => $this->module->version,
            'ps_version' => _PS_VERSION_,
            'php_version' => PHP_VERSION,
            'dependencies' => [],
        ];

        $dependencyFile = $this->module->getLocalPath() . self::DEPENDENCY_FILENAME;
        if (!file_exists($dependencyFile)) {
            throw new \Exception(self::DEPENDENCY_FILENAME . ' file is not found in ' . $this->module->getLocalPath());
        }

        $dependenciesContent = json_decode(file_get_contents($dependencyFile), true);
        if (json_last_error() != JSON_ERROR_NONE) {
            throw new \Exception(self::DEPENDENCY_FILENAME . ' file may be malformatted.');
        }

        if (empty($dependenciesContent['dependencies'])) {
            return $data;
        }

        foreach ($dependenciesContent['dependencies'] as $dependencyName => $dependencyMinVersion) {
            $dependencyData = \DbCore::getInstance()->getRow('SELECT `id_module`, `active`, `version` FROM `' . _DB_PREFIX_ . 'module` WHERE `name` = "' . pSQL($dependencyName) . '"');
            if (!$dependencyData) {
                $data['dependencies'][$dependencyName] = [
                    'min_version' => $dependencyMinVersion,
                    'installed' => false,
                ];
                continue;
            }
            $data['dependencies'][$dependencyName] = [
                'min_version' => $dependencyMinVersion,
                'installed' => true,
                'enabled' => (bool) $dependencyData['active'],
                'current_version' => $dependencyData['version'],
            ];
        }

        return $data;
    }
}
