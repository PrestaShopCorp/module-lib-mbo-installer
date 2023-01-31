<?php

namespace Prestashop\ModuleLibMboInstaller;

use PrestaShop\PrestaShop\Core\Addon\Module\ModuleManagerBuilder;
use PrestaShop\PrestaShop\Core\Module\ModuleManager;
use Prestashop\ModuleLibGuzzleAdapter\ClientFactory;
use GuzzleHttp\Psr7\Request;

use Exception;

class Installer
{
    public const ADDONS_URL = 'https://api-addons.prestashop.com';
    public const MODULE_ID = 39574;
    public const MODULE_NAME = 'ps_mbo';

    /**
     * @var ApiClient
     */
    protected $marketplaceClient;

    /**
     * @var ModuleManager
     */
    protected $moduleManager;

    /**
     * @var string
     */
    protected $prestashopVersion;

    /**
     *
     * @param string $prestashopVersion
     */
    public function __construct($prestashopVersion)
    {
        $this->marketplaceClient = (new ClientFactory())->getClient(['base_uri' => self::ADDONS_URL]);
        $this->moduleManager = ModuleManagerBuilder::getInstance()->build();
        $this->prestashopVersion = $prestashopVersion;

        if (!$this->moduleManager) {
            throw new \Exception('ModuleManagerBuilder::getInstance() failed');
        }
    }

    /**
     * Installs ps_mbo module
     *
     * @return boolean
     */
    public function installModule()
    {
        // On PrestaShop 1.7, the signature is install($source), with $source a module name or a path to an archive.
        // On PrestaShop 8, the signature is install(string $name, $source = null).
        if (version_compare($this->prestashopVersion, 8, '>=')) {
            return $this->moduleManager->install(self::MODULE_NAME, $this->downloadModule());
        }

        return $this->moduleManager->install(self::MODULE_NAME);
    }

    /**
     * Downloads ps_mbo module source from addons, store it and returns the file name
     *
     * @return string
     */
    private function downloadModule()
    {
        $params = [
            'id_module' => self::MODULE_ID,
            'channel' => 'stable',
            'method' => 'module',
            'version' => $this->prestashopVersion,
        ];

        $moduleData =  $this->marketplaceClient->sendRequest(
            new Request('POST', '/?'. http_build_query($params))
        )->getBody()->getContents();

        $temporaryZipFilename = tempnam(sys_get_temp_dir(), 'mod');

        if (file_put_contents($temporaryZipFilename, $moduleData) !== false) {
            return $temporaryZipFilename;
        } else {
            throw new Exception('Cannot store module content in temporary file !');
        }
    }
}
