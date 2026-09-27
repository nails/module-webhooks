<?php

namespace Tests\Webhooks\Fixture\Ingress\Webhooks;

use Nails\Webhooks\Delivery;
use Nails\Webhooks\Interfaces\Webhook;
use Nails\Webhooks\Result;
use Nails\Webhooks\Traits\AllowsDuplicates;
use Nails\Webhooks\Traits\Defaults;

class Repeatable implements Webhook
{
    use Defaults;
    use AllowsDuplicates;

    public static int $calls = 0;

    public function getLabel(): string
    {
        return 'Repeatable';
    }

    public function handle(Delivery $oDelivery): Result
    {
        self::$calls++;

        return Result::accepted('again');
    }
}
