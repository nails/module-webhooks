<?php

namespace Nails\Webhooks\Interfaces;

use Nails\Webhooks\Resource\Instance;

interface Subscribable
{
    public function subscribe(Instance $oInstance): void;

    public function unsubscribe(Instance $oInstance): void;

    public function subscriptionStatus(Instance $oInstance): string;
}
