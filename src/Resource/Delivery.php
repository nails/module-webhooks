<?php

namespace Nails\Webhooks\Resource;

use Nails\Common\Resource\Entity;

class Delivery extends Entity
{
    public ?string $uuid = null;

    public ?string $definition_class = null;

    public ?string $definition_slug = null;

    public int|string|null $instance_id = null;

    public ?string $scope = null;

    public ?string $status = null;

    public int|string|null $http_status = null;

    public ?string $idempotency_key = null;

    public ?string $summary = null;

    public int|string|null $duration_ms = null;

    public ?string $log_file = null;
}
