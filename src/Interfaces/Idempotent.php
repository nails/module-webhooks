<?php

namespace Nails\Webhooks\Interfaces;

use Nails\Webhooks\Delivery;

interface Idempotent
{
    /**
     * A stable identity for this request. Null keeps the raw-body hash.
     */
    public function getIdempotencyKey(Delivery $oDelivery): ?string;
}
