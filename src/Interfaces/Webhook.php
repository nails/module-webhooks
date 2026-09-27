<?php

namespace Nails\Webhooks\Interfaces;

use Nails\Webhooks\Delivery;
use Nails\Webhooks\Result;

interface Webhook
{
    public function getLabel(): string;

    public function getDescription(): string;

    public function isEnabled(): bool;

    public function handle(Delivery $oDelivery): Result;
}
