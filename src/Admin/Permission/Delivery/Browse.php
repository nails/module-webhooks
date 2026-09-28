<?php

namespace Nails\Webhooks\Admin\Permission\Delivery;

use Nails\Admin\Interfaces\Permission;

class Browse implements Permission
{
    public function label(): string
    {
        return 'Can browse webhook deliveries';
    }

    public function group(): string
    {
        return 'Webhooks';
    }
}
