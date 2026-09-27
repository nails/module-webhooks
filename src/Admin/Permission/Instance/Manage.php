<?php

namespace Nails\Webhooks\Admin\Permission\Instance;

use Nails\Admin\Interfaces\Permission;

class Manage implements Permission
{
    public function label(): string
    {
        return 'Can manage webhook instances';
    }

    public function group(): string
    {
        return 'Webhooks';
    }
}
