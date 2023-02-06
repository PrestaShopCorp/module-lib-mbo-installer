<?php

namespace Prestashop\ModuleLibMboInstaller;

class Presenter
{
    /**
     * @return array<string, string|boolean|null>
     */
    public function present()
    {
        /**
         * @var \Module|null
         */
        $mboModule = \Module::getInstanceByName(Installer::MODULE_NAME);

        $version = null;
        if ($mboModule) {
            $version = $mboModule->version;
        }

        return [
            'isPresentOnDisk' => (bool) $mboModule,
            'isInstalled' => ($mboModule && \Module::isInstalled(Installer::MODULE_NAME)),
            'isEnabled' => ($mboModule && \Module::isEnabled(Installer::MODULE_NAME)),
            'version' => $version,
        ];
    }
}
