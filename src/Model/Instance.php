<?php

namespace Nails\Webhooks\Model;

use Nails\Common\Model\Base;
use Nails\Webhooks\Constants;
use Nails\Webhooks\Resource;
use Nails\Webhooks\Store\InstanceStore;

class Instance extends Base implements InstanceStore
{
    const TABLE             = NAILS_DB_PREFIX . 'webhook_instance';
    const RESOURCE_NAME     = 'Instance';
    const RESOURCE_PROVIDER = Constants::MODULE_SLUG;
    const DESTRUCTIVE_DELETE = false;
    const AUTO_SET_TOKEN    = false;
    const DEFAULT_SORT_COLUMN = 'created';

    public function findByToken(string $sToken): ?Resource\Instance
    {
        $oItem = $this->getByToken($sToken);

        return $oItem instanceof Resource\Instance ? $oItem : null;
    }

    protected function prepareWriteData(array &$aData): Base
    {
        if (array_key_exists('config', $aData) && (is_array($aData['config']) || is_object($aData['config']))) {
            $aData['config'] = json_encode($aData['config']);
        }

        return parent::prepareWriteData($aData);
    }
}
