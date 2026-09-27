<?php

namespace Nails\Webhooks\Interfaces\Webhook;

use Nails\Webhooks\Delivery;
use Nails\Webhooks\Interfaces\Protection;
use Nails\Webhooks\Interfaces\Webhook;

interface ProtectedWebhook extends Webhook
{
    public function getProtection(Delivery $oDelivery): Protection;
}
