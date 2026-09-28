<?php

namespace Tests\Webhooks\Fixture\Ingress\Webhooks;

use Nails\Webhooks\Delivery;
use Nails\Webhooks\Interfaces\Protection;
use Nails\Webhooks\Interfaces\Webhook\ProtectedWebhook;
use Nails\Webhooks\Protection\HmacSignature;
use Nails\Webhooks\Result;
use Nails\Webhooks\Traits\Defaults;

class Signed implements ProtectedWebhook
{
    use Defaults;

    public const SECRET = 'top-secret';

    public static int $calls = 0;

    public function getLabel(): string
    {
        return 'Signed';
    }

    public function getProtection(Delivery $oDelivery): Protection
    {
        return new HmacSignature(self::SECRET, 'X-Signature');
    }

    public function handle(Delivery $oDelivery): Result
    {
        self::$calls++;

        return Result::accepted('signed');
    }
}
