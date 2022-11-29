<?php

namespace Prestashop\AddonsHelper;

use PrestaShop\PrestaShop\Core\Addon\Module\ModuleManagerBuilder;
use Prestashop\ModuleLibGuzzleAdapter\ClientFactory;
use GuzzleHttp\Psr7\Request;

use ZipArchive;

class AddonsHelper
{
    public const MODULE_TABLE = 'module';
    public const MODULE_TABLE_HISTORY = 'module_history';
    public const MODULE_SHOP = 'module_shop';
    public const ADDONS_URL = 'https://api-addons.prestashop.com';

    /**
     * @var ApiClient
     */
    protected $marketplaceClient;

    /**
     * @var \Db
     */
    private $db;

    public function __construct()
    {
        $this->db = \Db::getInstance();
        $this->marketplaceClient = (new ClientFactory())->getClient(['base_uri' => self::ADDONS_URL]);
    }

    /**
     * @return \DbQuery
     */
    public function getBaseQuery()
    {
        return (new \DbQuery())
            ->from(self::MODULE_TABLE, 'm')
            ->leftJoin(self::MODULE_TABLE_HISTORY, 'h', 'm.id_module = h.id_module')
            ->leftJoin(self::MODULE_SHOP, 'm_shop', 'm.id_module = m_shop.id_module');
    }

    /**
     * @param int $offset
     * @param int $limit
     *
     * @return array|bool|false|\mysqli_result|\PDOStatement|resource|null
     *
     * @throws \PrestaShopDatabaseException
     */
    public function getModules($offset, $limit)
    {
        $query = $this->getBaseQuery();

        /*
         * The `active` field of the "ps_module" table has been deprecated, this is why we use the "ps_module_shop" table
         * to check if a module is active or not
        */
        $query->select('m.id_module as module_id, name, version as module_version, IF(m_shop.enable_device, 1, 0) as active, date_add as created_at, date_upd as updated_at')
            ->limit($limit, $offset);

        return $this->db->executeS($query);
    }

    /**
     * Downloads a module source from addons, store it and returns the file name
     */
    public function downloadModule(int $moduleId): string
    {

        try {
            $params = [
                'id_module' => $moduleId,
                'channel' => 'stable',
                'method' => 'module',
            ];

            // TODO add prestashop version in params
            $moduleData =  $this->marketplaceClient->sendRequest(
                new Request('POST', '/?id_module=' . $moduleId . '&channel=stable&method=module')
            )->getBody()->getContents();
        } catch (Exception $e) {
            throw $e;
        }

        $temporaryZipFilename = tempnam(sys_get_temp_dir(), 'mod');
        if (file_put_contents($temporaryZipFilename, $moduleData) !== false) {
            return $temporaryZipFilename;
        } else {
            throw new Exception('Cannot store module content in temporary file !');
        }
    }

    function getModuleId($moduleName)
    {
        $modules = [
            'ps_eventbus' => 50756
        ];

        return $modules[$moduleName];
    }

    /**
     * @param string $moduleName
     * @param string $version
     *
     * @return string||null
     *
     * @throws \PrestaShopDatabaseException
     */
    public function installModule($moduleName, $version = 'latest')
    {
        if (version_compare(_PS_VERSION_, '8', '>=')) {
            // Get module id
            $moduleId = $this->getModuleId($moduleName);
            $zipPath =  $this->downloadModule($moduleId, $version);
            $this->unzipModule($zipPath);
            unlink($zipPath);
        }

        $moduleManager = ModuleManagerBuilder::getInstance();
        if (!$moduleManager) {
            throw new \Exception('ModuleManagerBuilder::getInstance() failed');
            return 'no manager';
        }
        $moduleManager = $moduleManager->build();
        if ($moduleManager->install($moduleName)) {
            return \Module::getInstanceByName($moduleName);
        }
        return 'no module';
    }

    public function unzipModule($zipPath)
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true || !$zip->extractTo(_PS_MODULE_DIR_) || !$zip->close()) {
            throw new Exception('Cannot extract module content from temporary file !');
        }
    }

    /**
     * {@inheritdoc}
     *
     * @throws Exception
     */
    public function request(string $action, array $params = [])
    {

        if ($action === 'module_download') {
            $params['channel'] = 'stable';
            $params['method'] = 'module';
        }


        try {
            $this->marketplaceClient->sendRequest(
                new Request('POST', '', ['query' => $params])
            );
            // return $this->marketplaceClient->request('POST', '', ['query' => $params])->getBody();
        } catch (Exception $e) {
            self::$is_addons_up = false;

            throw $e;
        }
    }
    /**
     * @return string
     *
     */
    public function expose()
    {
        return [
            'test' => \Context::getContext()->link->getModuleLink('ps_tech_vendor_boilerplate', 'Module', array('idPayment' => 1337, 'ajax' => true)),
            'installLink' =>  \Tools::getHttpHost(true) . '/modules/' . '/',
        ];
    }
}
