<?php

namespace Tests\Webhooks\Fixture\Ingress\Webhooks;

use Nails\Webhooks\Delivery;
use Nails\Webhooks\Interfaces\Protection;
use Nails\Webhooks\Result;

class ChallengeProtection implements Protection
{
    public function verify(Delivery $oDelivery): ?Result
    {
        return Result::challenge('pong', 200, ['X-Challenge' => '1']);
    }

    public function sensitiveHeaders(): array
    {
        return [];
    }
}
