<?php

namespace Nails\Webhooks\Store;

interface DeliveryLog
{
    public function line(string $sUuid, string $sLine): void;

    public function path(): string;
}
