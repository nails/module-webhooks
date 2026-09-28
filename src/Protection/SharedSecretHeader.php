<?php

namespace Nails\Webhooks\Protection;

use Nails\Webhooks\Delivery;
use Nails\Webhooks\Exception\RejectedException;
use Nails\Webhooks\Interfaces\Protection;
use Nails\Webhooks\Result;

class SharedSecretHeader implements Protection
{
    public function __construct(
        private readonly string $sSecret,
        private readonly string $sHeader = 'X-Webhook-Token',
        private readonly bool $bAllowQuery = false,
    ) {
    }

    public function verify(Delivery $oDelivery): ?Result
    {
        $sGiven = (string) $oDelivery->header($this->sHeader);
        if ($sGiven === '' && $this->bAllowQuery) {
            $mQuery = $oDelivery->query['token'] ?? '';
            $sGiven = is_string($mQuery) ? $mQuery : '';
        }

        if ($this->sSecret === '' || $sGiven === '' || !hash_equals($this->sSecret, $sGiven)) {
            throw new RejectedException('Shared secret mismatch');
        }

        return null;
    }

    public function sensitiveHeaders(): array
    {
        return [$this->sHeader];
    }
}
