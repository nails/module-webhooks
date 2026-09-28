<?php

namespace Nails\Webhooks\Admin\Permission\Delivery;

use Nails\Admin\Interfaces\Permission;

class View implements Permission
{
    public function label(): string
    {
        return 'Can view a webhook delivery, including the file log';
    }

    public function group(): string
    {
        return 'Webhooks';
    }
}
