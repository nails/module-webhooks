<?php

namespace Nails\Webhooks\Admin\Permission\Definition;

use Nails\Admin\Interfaces\Permission;

class Browse implements Permission
{
    public function label(): string
    {
        return 'Can browse webhook definitions';
    }

    public function group(): string
    {
        return 'Webhooks';
    }
}
