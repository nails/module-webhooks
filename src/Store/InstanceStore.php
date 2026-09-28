<?php

namespace Nails\Webhooks\Store;

use Nails\Webhooks\Resource\Instance;

interface InstanceStore
{
    public function findByToken(string $sToken): ?Instance;
}
