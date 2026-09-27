<?php

namespace Tests\Webhooks\Fixture\Ingress\Webhooks;

use Nails\Webhooks\Delivery;
use Nails\Webhooks\Interfaces\Webhook;
use Nails\Webhooks\Result;
use Nails\Webhooks\Traits\Defaults;
use RuntimeException;

class Throws implements Webhook
{
    use Defaults;

    public static int $calls = 0;

    public function getLabel(): string
    {
        return 'Throws';
    }

    public function handle(Delivery $oDelivery): Result
    {
        self::$calls++;

        throw new RuntimeException('boom');
    }
}
