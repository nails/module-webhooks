<?php

namespace Tests\Webhooks\Fixture\Ingress\Webhooks;

use Nails\Webhooks\Delivery;
use Nails\Webhooks\Interfaces\Webhook;
use Nails\Webhooks\Result;
use Nails\Webhooks\Traits\Configurable;
use Nails\Webhooks\Traits\Defaults;

class PostToChannel implements Webhook
{
    use Defaults;
    use Configurable;

    public static int $calls = 0;

    public function getLabel(): string
    {
        return 'Post to channel';
    }

    public function getConfigFields(): array
    {
        return [
            'channel' => [
                'label' => 'Channel',
                'rules' => 'required',
            ],
        ];
    }

    public function handle(Delivery $oDelivery): Result
    {
        self::$calls++;

        return Result::accepted('posted');
    }
}
