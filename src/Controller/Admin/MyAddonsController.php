<?php

namespace Prestashop\AddonsHelper\Controller\Admin;

use Prestashop\AddonsHelper\AddonsHelper;

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

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
     * Get info
     *
     * @return Response
     */
    public function installModule(Request $request)
    {

        if (!is_string($request->getContent(false))) {
            throw new \PrestaShopException('Invalid request');
        }

        $requestBodyContent = json_decode($request->getContent(false), true);

        $installed = $this->addonsHelper->installModule($requestBodyContent['module_name']);
        if ($installed) {

            return new Response(
                json_encode([
                    'success' => 'true',
                    'message' => 'Module installed successfully by addons',
                ]),
                200,
                ['Content-Type' => 'application/json']
            );
        }

        return new Response(
            json_encode([
                'success' => 'false',
                'error' => 'Module can\'t be installed  by addons',
            ]),
            200,
            ['Content-Type' => 'application/json']
        );
    }
}
