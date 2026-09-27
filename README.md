# Webhooks Module for Nails

![license](https://img.shields.io/badge/license-MIT-green.svg)
[![CircleCI branch](https://img.shields.io/circleci/project/github/nails/module-webhooks.svg)](https://circleci.com/gh/nails/module-webhooks)

This module brings webhook functionality to Nails. A component exposes an endpoint by adding a class under `src/Webhooks` that implements `Nails\Webhooks\Interfaces\Webhook`. The module discovers it, verifies the request, and records the outcome.

The implementation plan is in [.agents/features/webhooks.md](.agents/features/webhooks.md).

## Handlers

The public URL is derived from the component package and the class path under `Webhooks`. `\Nails\Invoice\Webhooks\PaymentNotification` is served at `/webhooks/nails/module-invoice/payment-notification`. An app class `\App\Webhooks\PostToChannel` is served at `/webhooks/app/post-to-channel`.

```php
namespace App\Webhooks;

use Nails\Webhooks\Delivery;
use Nails\Webhooks\Interfaces\Webhook;
use Nails\Webhooks\Interfaces\Webhook\ProtectedWebhook;
use Nails\Webhooks\Result;
use Nails\Webhooks\Traits\Configurable;
use Nails\Webhooks\Traits\Defaults;
use Nails\Webhooks\Traits\SharedSecret;

class PostToChannel implements Webhook, ProtectedWebhook
{
    use Defaults;
    use Configurable;
    use SharedSecret;

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

    protected function secret(Delivery $oDelivery): string
    {
        return (string) $oDelivery->instance()->secret;
    }

    public function handle(Delivery $oDelivery): Result
    {
        $sChannel = (string) $this->config($oDelivery, 'channel');

        return Result::accepted('Posted to ' . $sChannel);
    }
}
```

`Configurable` means each admin instance supplies its own channel, secret, and URL token. Without that trait the handler is a single endpoint, which is the shape a payment driver uses for one platform account.

`SharedSecret` compares the `X-Webhook-Token` header. `SignsPayload` checks an HMAC of the raw body, including Stripe's `t=<unix>,v1=<hex>` header when `signsTimestamp()` returns true.

A repeat of a request that already succeeded is recorded as `ignored` and `handle()` is not called again. Use `Nails\Webhooks\Traits\AllowsDuplicates` when a repeat should run anyway.

## Payment notifications

`driver-invoice-stripe` would add `src/Stripe/Webhooks/PaymentNotification.php`. The derived URL is `/webhooks/nails/driver-invoice-stripe/payment-notification`. The handler uses `SignsPayload` in timestamp mode with the header `Stripe-Signature`, and `handle()` completes or refunds the payment. That class lives in the driver, not in this module.
