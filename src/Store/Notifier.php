<?php

namespace Nails\Webhooks\Store;

interface Notifier
{
    public function completed(int $iDeliveryId): void;
}
