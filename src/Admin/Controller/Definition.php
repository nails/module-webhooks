<?php

namespace Nails\Webhooks\Admin\Controller;

use Nails\Admin\Controller\Base;
use Nails\Admin\Factory\Nav;
use Nails\Factory;
use Nails\Webhooks\Admin\Permission;
use Nails\Webhooks\Constants;
use Nails\Webhooks\Service\Webhook;

class Definition extends Base
{
    public static function announce(): Nav|array|null
    {
        if (!userHasPermission(Permission\Definition\Browse::class)) {
            return null;
        }

        /** @var Nav $oNavGroup */
        $oNavGroup = Factory::factory('Nav', \Nails\Admin\Constants::MODULE_SLUG);
        $oNavGroup
            ->setLabel('Webhooks')
            ->setIcon('fa-plug')
            ->addAction('Definitions');

        return $oNavGroup;
    }

    public function index(): void
    {
        if (!userHasPermission(Permission\Definition\Browse::class)) {
            unauthorised();
        }

        /** @var Webhook $oWebhooks */
        $oWebhooks = Factory::service('Webhook', Constants::MODULE_SLUG);

        $this
            ->addBreadcrumb('Webhooks')
            ->addBreadcrumb('Definitions')
            ->setData('aDefinitions', $oWebhooks->all())
            ->setData('sDeliveryUrl', Delivery::url())
            ->loadView('index');
    }
}
