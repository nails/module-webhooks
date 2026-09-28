<?php

use Nails\Webhooks\Model;
use Nails\Webhooks\Resource;
use Nails\Webhooks\Service;

return [
    'services'  => [
        'Webhook' => function (): Service\Webhook {
            if (class_exists('\App\Webhooks\Service\Webhook')) {
                return new \App\Webhooks\Service\Webhook();
            }

            return new Service\Webhook();
        },
        'Ingress' => function (): Service\Ingress {
            if (class_exists('\App\Webhooks\Service\Ingress')) {
                return new \App\Webhooks\Service\Ingress();
            }

            return new Service\Ingress();
        },
        'Log'     => function (): Service\Log {
            if (class_exists('\App\Webhooks\Service\Log')) {
                return new \App\Webhooks\Service\Log();
            }

            return new Service\Log();
        },
    ],
    'models'    => [
        'Instance' => function (): Model\Instance {
            if (class_exists('\App\Webhooks\Model\Instance')) {
                return new \App\Webhooks\Model\Instance();
            }

            return new Model\Instance();
        },
        'Delivery' => function (): Model\Delivery {
            if (class_exists('\App\Webhooks\Model\Delivery')) {
                return new \App\Webhooks\Model\Delivery();
            }

            return new Model\Delivery();
        },
    ],
    'resources' => [
        'Instance' => function ($resource): Resource\Instance {
            if (class_exists('\App\Webhooks\Resource\Instance')) {
                return new \App\Webhooks\Resource\Instance($resource);
            }

            return new Resource\Instance($resource);
        },
        'Delivery' => function ($resource): Resource\Delivery {
            if (class_exists('\App\Webhooks\Resource\Delivery')) {
                return new \App\Webhooks\Resource\Delivery($resource);
            }

            return new Resource\Delivery($resource);
        },
    ],
];
