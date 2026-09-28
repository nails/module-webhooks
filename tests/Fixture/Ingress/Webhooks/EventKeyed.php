<?php

namespace Tests\Webhooks\Fixture\Ingress\Webhooks;

use Nails\Webhooks\Delivery;
use Nails\Webhooks\Interfaces\Idempotent;
use Nails\Webhooks\Interfaces\Webhook;
use Nails\Webhooks\Result;
use Nails\Webhooks\Traits\Defaults;

class EventKeyed implements Idempotent, Webhook
{
    use Defaults;

    public static int $calls = 0;

    public function getLabel(): string
    {
        return 'Event keyed';
    }

    public function getIdempotencyKey(Delivery $oDelivery): ?string
    {
        $oJson = $oDelivery->json();

        return isset($oJson->id) ? (string) $oJson->id : null;
    }

    public function handle(Delivery $oDelivery): Result
    {
        self::$calls++;

        return Result::accepted('event');
    }
}
