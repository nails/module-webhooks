<?php

namespace Nails\Webhooks;

use Nails\Common\Events\Base;

class Events extends Base
{
    /**
     * Fired after a delivery audit row is written
     *
     * @param int|string $mDeliveryId The delivery row id
     */
    const DELIVERY_COMPLETED = 'delivery.completed';
}
