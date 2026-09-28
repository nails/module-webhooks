<?php

namespace Tests\Webhooks\Fixture\Ingress\Webhooks;

use Nails\Webhooks\Delivery;
use Nails\Webhooks\Interfaces\Protection;
use Nails\Webhooks\Interfaces\Webhook\ProtectedWebhook;
use Nails\Webhooks\Result;
use Nails\Webhooks\Traits\Defaults;

class Challenge implements ProtectedWebhook
{
    use Defaults;

    public static int $calls = 0;

    public function getLabel(): string
    {
        return 'Challenge';
    }

    public function getProtection(Delivery $oDelivery): Protection
    {
        return new ChallengeProtection();
    }

    public function handle(Delivery $oDelivery): Result
    {
        self::$calls++;

        return Result::accepted('handled');
    }
}
