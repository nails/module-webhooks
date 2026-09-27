<?php

namespace Nails\Webhooks;

use Nails\Common\Interfaces\RouteGenerator;

class Routes implements RouteGenerator
{
    /**
     * @return string[]
     */
    public static function generate(): array
    {
        return [
            'webhooks/(.+)' => 'webhooks/ingress/index/$1',
        ];
    }
}
