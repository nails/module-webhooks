<?php

namespace Tests\Webhooks\Support;

use Nails\Webhooks\Store\DeliveryLog;

class MemoryLog implements DeliveryLog
{
    /** @var string[] */
    public array $lines = [];

    public function line(string $sUuid, string $sLine): void
    {
        $this->lines[] = $sUuid . ' ' . $sLine;
    }

    public function path(): string
    {
        return 'log-test.php';
    }
}
