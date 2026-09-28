<?php

namespace Nails\Webhooks\Admin\Controller;

use Nails\Admin\Controller\DefaultController;
use Nails\Admin\Factory\IndexFilter;
use Nails\Factory;
use Nails\Webhooks\Admin\Permission;
use Nails\Webhooks\Constants;
use Nails\Webhooks\Resource\Delivery as DeliveryResource;
use Nails\Webhooks\Result;
use Nails\Webhooks\Service\Log;

class Delivery extends DefaultController
{
    const CONFIG_MODEL_NAME      = 'Delivery';
    const CONFIG_MODEL_PROVIDER  = Constants::MODULE_SLUG;
    const CONFIG_SIDEBAR_GROUP   = 'Webhooks';
    const CONFIG_SIDEBAR_ICON    = 'fa-plug';
    const CONFIG_SIDEBAR_FORMAT  = '%s';
    const CONFIG_TITLE_SINGLE    = 'Delivery';
    const CONFIG_TITLE_PLURAL    = 'Deliveries';
    const CONFIG_PERMISSION_BROWSE = Permission\Delivery\Browse::class;
    const CONFIG_CAN_CREATE      = false;
    const CONFIG_CAN_EDIT        = false;
    const CONFIG_CAN_DELETE      = false;
    const CONFIG_CAN_RESTORE     = false;
    const CONFIG_CAN_DESTROY     = false;
    const CONFIG_CAN_COPY        = false;
    const CONFIG_CAN_VIEW        = false;
    const CONFIG_SORT_DIRECTION  = self::SORT_DESCENDING;
    const CONFIG_SORT_OPTIONS    = [
        'Received' => 'created',
        'Status'   => 'status',
    ];
    const CONFIG_INDEX_FIELDS    = [
        'Received'   => 'created',
        'Definition' => 'definition_slug',
        'Status'     => 'status',
        'HTTP'       => 'http_status',
        'Summary'    => 'summary',
        'Duration'   => 'duration_ms',
    ];

    public function __construct()
    {
        parent::__construct();

        $this->addIndexRowButton(
            'log/{{id}}',
            'Log',
            'btn-default',
            null,
            Permission\Delivery\View::class,
        );
    }

    /**
     * @return IndexFilter[]
     */
    protected function indexDropdownFilters(): array
    {
        $aFilters = parent::indexDropdownFilters();

        /** @var IndexFilter $oFilter */
        $oFilter = Factory::factory('IndexFilter', \Nails\Admin\Constants::MODULE_SLUG);
        $oFilter
            ->setLabel('Status')
            ->setColumn('status');

        $oAny = Factory::factory('IndexFilterOption', \Nails\Admin\Constants::MODULE_SLUG);
        $oAny->setLabel('Any status');
        $oFilter->addOption($oAny);

        foreach ([
            Result::ACCEPTED,
            Result::IGNORED,
            Result::FAILED,
            Result::CHALLENGE,
            'rejected',
        ] as $sStatus) {
            $oOption = Factory::factory('IndexFilterOption', \Nails\Admin\Constants::MODULE_SLUG);
            $oOption
                ->setLabel(ucfirst($sStatus))
                ->setValue($sStatus);
            $oFilter->addOption($oOption);
        }

        $aFilters[] = $oFilter;

        return $aFilters;
    }

    public function log(int $iId): void
    {
        if (!userHasPermission(Permission\Delivery\View::class)) {
            unauthorised();
        }

        /** @var DeliveryResource|null $oDelivery */
        $oDelivery = $this->getModel()->getById($iId);
        if (!$oDelivery) {
            show404();
            exit;
        }

        /** @var Log $oLog */
        $oLog = Factory::service('Log', Constants::MODULE_SLUG);

        $this
            ->addBreadcrumb('Webhooks')
            ->addBreadcrumb('Deliveries', static::url())
            ->addBreadcrumb($oDelivery->uuid ?? (string) $iId)
            ->setData('oDelivery', $oDelivery)
            ->setData('sLog', $oLog->excerpt((string) $oDelivery->log_file, (string) $oDelivery->uuid))
            ->loadView('log');
    }
}
