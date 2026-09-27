---
description: >-
  Receive HTTP callbacks from other services, verify them, and keep an audit of
  every delivery.
---

# Webhooks

The webhooks module is the way a Nails app receives an HTTP callback. A class under `src/Webhooks` is the endpoint. The module discovers it, checks the request, calls your handler, and records what happened.

```bash
composer require nails/module-webhooks
```

A payment driver is the usual shape: Stripe calls back when a payment succeeds, the handler checks the signature, and `handle()` marks the payment complete. That handler lives in the driver, not in this module. [Invoice](invoice/README.md) is not a dependency.

## A request

1. The provider calls `/webhooks/{slug}`, or `/webhooks/{slug}/{token}` when the handler has [instances](webhooks/instances.md).
2. An unknown, disabled, or badly addressed handler answers **404**. Nothing is written down.
3. A [protected](webhooks/protection.md) handler verifies the request. A bad signature is **401** and `handle()` is not called. A handshake (Slack `url_verification`, Meta `hub.challenge`) is answered immediately.
4. A repeat of a delivery that already succeeded is recorded as ignored and `handle()` is not called again. See [Deliveries](webhooks/deliveries.md).
5. `handle()` returns a [result](webhooks/handlers.md#results). The HTTP status comes from that result. An exception is **500**, so the provider retries.
6. One audit row is stored, a line is written to the day's file log, and `delivery.completed` is triggered.

GET and POST are accepted. Anything else is **405**.

{% hint style="warning" %}
CSRF protection is off in Nails by default. If the app turns it on, exclude `webhooks/.*`. The provider cannot send a CSRF token.
{% endhint %}

## A handler

Drop a class in `src/Webhooks` that implements `Nails\Webhooks\Interfaces\Webhook`. `Nails\Webhooks\Traits\Defaults` fills in an empty description and leaves the handler enabled.

```php
namespace App\Webhooks;

use Nails\Webhooks\Delivery;
use Nails\Webhooks\Interfaces\Webhook;
use Nails\Webhooks\Result;
use Nails\Webhooks\Traits\Defaults;

class BuildFinished implements Webhook
{
    use Defaults;

    public function getLabel(): string
    {
        return 'Build finished';
    }

    public function handle(Delivery $oDelivery): Result
    {
        $oPayload = $oDelivery->json();
        $oDelivery->log('Build ' . ($oPayload->id ?? ''));

        return Result::accepted('Recorded build');
    }
}
```

That class is served at `/webhooks/app/build-finished`. You do not register it. Discovery, slugs, and return values are covered in [Handlers](webhooks/handlers.md).

Copy the URL from **Admin → Webhooks → Definitions**. Renaming or moving the class changes the URL, and there is no override that lets the two drift apart.

## Where to go next

| Page | What it covers |
|---|---|
| [Handlers](webhooks/handlers.md) | Discovery, the slug, `Delivery`, and `Result` |
| [Protection](webhooks/protection.md) | Shared secrets, HMAC signatures, challenges, and subscribing the URL with a provider |
| [Instances](webhooks/instances.md) | One class, many copies: each with its own channel, secret, and URL |
| [Deliveries](webhooks/deliveries.md) | Duplicate suppression, the file log, the audit table, and the admin screens |
