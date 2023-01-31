<?php

namespace Prestashop\ModuleLibMboInstaller;

use Module;

class Presenter
{
    public function present()
    {
        /**
         * @var Module|null
         */
        $mboModule = Module::getInstanceByName(Installer::MODULE_NAME);

        $version = null;
        if ($mboModule) {
            $version = $mboModule->version;
        }

        return [
            'isPresentOnDisk' => !!$mboModule,
            'isInstalled' => ($mboModule && Module::isInstalled(Installer::MODULE_NAME)),
            'isEnabled' => ($mboModule && Module::isEnabled(Installer::MODULE_NAME)),
            'version' => $version,
        ];
    }
}
