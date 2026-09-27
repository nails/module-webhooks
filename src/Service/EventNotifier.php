<?php

namespace Nails\Webhooks\Service;

use Nails\Factory;
use Nails\Webhooks\Events;
use Nails\Webhooks\Store\Notifier;

class EventNotifier implements Notifier
{
    public function completed(int $iDeliveryId): void
    {
        Factory::service('Event')->trigger(
            Events::DELIVERY_COMPLETED,
            Events::getEventNamespace(),
            [$iDeliveryId],
        );
    }
}
