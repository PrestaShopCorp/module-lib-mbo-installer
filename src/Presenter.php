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
        $mboModule = Module::getInstanceByName(AddonsHelper::MODULE_NAME);

        $version = null;
        if ($mboModule) {
            $version = $mboModule->version;
        }

        return [
            'isPresentOnDisk' => !!$mboModule,
            'isInstalled' => ($mboModule && Module::isInstalled(AddonsHelper::MODULE_NAME)),
            'isEnabled' => ($mboModule && Module::isEnabled(AddonsHelper::MODULE_NAME)),
            'version' => $version,
        ];
    }
}
