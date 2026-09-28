<?php

namespace Tests\Webhooks\Support;

use Nails\Webhooks\Resource\Instance;
use Nails\Webhooks\Store\InstanceStore;

class MemoryInstanceStore implements InstanceStore
{
    /** @var array<string, Instance> */
    private array $aByToken = [];

    public function add(Instance $oInstance): void
    {
        $this->aByToken[(string) $oInstance->token] = $oInstance;
    }

    public function findByToken(string $sToken): ?Instance
    {
        return $this->aByToken[$sToken] ?? null;
    }
}
