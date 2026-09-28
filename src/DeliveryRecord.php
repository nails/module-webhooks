<?php

namespace Nails\Webhooks;

class DeliveryRecord
{
    public function __construct(
        public readonly int $id,
        public readonly string $uuid,
        public readonly string $status,
    ) {
    }
}
