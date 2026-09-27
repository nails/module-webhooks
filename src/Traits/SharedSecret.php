<?php

namespace Nails\Webhooks\Traits;

use Nails\Webhooks\Delivery;
use Nails\Webhooks\Interfaces\Protection;
use Nails\Webhooks\Protection\SharedSecretHeader;

trait SharedSecret
{
    abstract protected function secret(Delivery $oDelivery): string;

    protected function secretHeader(): string
    {
        return 'X-Webhook-Token';
    }

    protected function allowsQueryToken(): bool
    {
        return false;
    }

    public function getProtection(Delivery $oDelivery): Protection
    {
        return new SharedSecretHeader(
            $this->secret($oDelivery),
            $this->secretHeader(),
            $this->allowsQueryToken(),
        );
    }
}
