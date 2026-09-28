<?php

namespace Tests\Webhooks\Support;

use Nails\Webhooks\Store\Notifier;

class MemoryNotifier implements Notifier
{
    /** @var int[] */
    public array $ids = [];

    public function completed(int $iDeliveryId): void
    {
        $this->ids[] = $iDeliveryId;
    }
}
