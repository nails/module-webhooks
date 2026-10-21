<?php

namespace Nails\Webhooks\FindReplace\Exclusion;

use Nails\FindReplace\Interfaces\Exclusion;

class Package implements Exclusion
{
    public function hidden(): array
    {
        return [
            NAILS_DB_PREFIX . 'webhook_delivery',
            NAILS_DB_PREFIX . 'webhook_instance.secret',
            NAILS_DB_PREFIX . 'webhook_instance.config',
        ];
    }

    public function readOnly(): array
    {
        return [
            NAILS_DB_PREFIX . 'webhook_instance.definition_class',
            NAILS_DB_PREFIX . 'webhook_instance.definition_slug',
            NAILS_DB_PREFIX . 'webhook_instance.subscription_status',
        ];
    }
}
