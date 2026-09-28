<?php

namespace Tests\Webhooks\Fixture\Alpha\Webhooks;

use Nails\Webhooks\Delivery;
use Nails\Webhooks\Interfaces\Webhook;
use Nails\Webhooks\Result;
use Nails\Webhooks\Traits\Defaults;

class PaymentNotification implements Webhook
{
    use Defaults;

    public static int $calls = 0;

    public function getLabel(): string
    {
        return 'Alpha payment';
    }

    public function handle(Delivery $oDelivery): Result
    {
        self::$calls++;

        return Result::accepted('alpha');
    }
}
