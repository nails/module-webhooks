<?php

namespace Nails\Webhooks\Traits;

use Nails\Webhooks\Delivery;

trait Configurable
{
    /**
     * @return array<string, array<string, mixed>>
     */
    abstract public function getConfigFields(): array;

    public function config(Delivery $oDelivery, string $sKey, mixed $mDefault = null): mixed
    {
        $oInstance = $oDelivery->instance();
        if ($oInstance === null) {
            return $mDefault;
        }

        return $oInstance->config($sKey, $mDefault);
    }
}
