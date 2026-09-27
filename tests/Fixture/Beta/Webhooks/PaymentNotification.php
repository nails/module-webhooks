<?php

namespace Tests\Webhooks\Fixture\Beta\Webhooks;

use Nails\Webhooks\Delivery;
use Nails\Webhooks\Interfaces\Webhook;
use Nails\Webhooks\Result;
use Nails\Webhooks\Traits\Defaults;

class PaymentNotification implements Webhook
{
    use Defaults;

    public function getLabel(): string
    {
        return 'Beta payment';
    }

    public function handle(Delivery $oDelivery): Result
    {
        return Result::accepted('beta');
    }
}
