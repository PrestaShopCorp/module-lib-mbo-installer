<?php

namespace Prestashop\AddonsHelper\Controller\Admin;

use Prestashop\AddonsHelper\AddonsHelper;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MyAddonsController extends FrameworkBundleAdminController
{
    /**
     * @var AddonsHelper
     */
    private $addonsHelper;

    public function __construct()
    {
        parent::__construct();
        $this->addonsHelper = new AddonsHelper();
    }

    /**
     * install a module
     *
     * @return Response
     */
    public function installModule(Request $request)
    {
        if (!is_string($request->getContent(false))) {
            throw new \PrestaShopException('Invalid request');
        }

        $requestBodyContent = json_decode($request->getContent(false), true);

        $installed = $this->addonsHelper->installModule($requestBodyContent['moduleName']);
        if ($installed) {
            return new Response(
                json_encode([
                    'success' => 'true',
                    'message' => 'Module ' . $requestBodyContent['moduleName'] . ' successfully by addonsHelper',
                ]),
                200,
                ['Content-Type' => 'application/json']
            );
        }

        return new Response(
            json_encode([
                'success' => 'false',
                'error' => 'Module can\'t be installed  by addonsHelper',
            ]),
            500,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * Enable a module
     *
     * @return Response
     */
    public function enableModule(Request $request)
    {
        if (!is_string($request->getContent(false))) {
            throw new \PrestaShopException('Invalid request');
        }

        $requestBodyContent = json_decode($request->getContent(false), true);

        $installed = $this->addonsHelper->enableModule($requestBodyContent['moduleName']);
        if ($installed) {
            return new Response(
                json_encode([
                    'success' => 'true',
                    'message' => 'Module ' . $requestBodyContent['moduleName'] . ' successfully by addonsHelper',
                ]),
                200,
                ['Content-Type' => 'application/json']
            );
        }

        return new Response(
            json_encode([
                'success' => 'false',
                'error' => 'Module can\'t be enabled by addonsHelper',
            ]),
            500,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * Get a module
     *
     * @return Response
     */
    public function getModule(Request $request)
    {
        if (!is_string($request->getContent(false))) {
            throw new \PrestaShopException('Invalid request');
        }
        $moduleName = $request->query->get('moduleName');

        $module = $this->addonsHelper->getModule($moduleName);
        if ($module) {
            return new Response(
                json_encode($module),
                200,
                ['Content-Type' => 'application/json']
            );
        }

        return new Response(
            json_encode([
                'success' => 'false',
                'error' => 'Module ' . $moduleName . ' can\'t be found by addonsHelper',
            ]),
            404,
            ['Content-Type' => 'application/json']
        );
    }
}
