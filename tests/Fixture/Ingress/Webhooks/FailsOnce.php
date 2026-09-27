<?php

namespace Tests\Webhooks\Fixture\Ingress\Webhooks;

use Nails\Webhooks\Delivery;
use Nails\Webhooks\Interfaces\Webhook;
use Nails\Webhooks\Result;
use Nails\Webhooks\Traits\Defaults;

class FailsOnce implements Webhook
{
    use Defaults;

    public static int $calls = 0;

    public function getLabel(): string
    {
        return 'Fails once';
    }

    public function handle(Delivery $oDelivery): Result
    {
        self::$calls++;
        if (self::$calls === 1) {
            return Result::failed('nope');
        }

        return Result::accepted('recovered');
    }
}
