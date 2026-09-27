<?php

namespace Tests\Webhooks\Fixture\Ingress\Webhooks;

use Nails\Webhooks\Delivery;
use Nails\Webhooks\Interfaces\Webhook;
use Nails\Webhooks\Result;
use Nails\Webhooks\Traits\Defaults;

class Disabled implements Webhook
{
    use Defaults;

    public static int $calls = 0;

    public function getLabel(): string
    {
        return 'Disabled';
    }

    public function isEnabled(): bool
    {
        return false;
    }

    public function handle(Delivery $oDelivery): Result
    {
        self::$calls++;

        return Result::accepted('should not run');
    }
}
