---
description: >-
  Verify a webhook with a shared secret or an HMAC signature, answer a
  subscribe challenge, or register the URL with the provider.
---

# Protection

A handler that only implements `Webhook` is **simple**. Ingress calls `handle()` as soon as the URL resolves. Use that when the sender cannot sign, or when the URL itself is the only secret you are willing to rely on. The slug is not a secret: it is derived from the class name and shown in admin.

A handler that also implements `Nails\Webhooks\Interfaces\Webhook\ProtectedWebhook` is **protected**. It returns a `Protection` for the delivery. `verify()` runs before `handle()`.

```php
interface ProtectedWebhook extends Webhook
{
    public function getProtection(Delivery $oDelivery): Protection;
}
```

`Protection::verify()` has three outcomes:

- `null` — the request is genuine and handling continues.
- a `Result` — ingress returns that result and does not call `handle()`. This is how a subscribe challenge answers.
- `Nails\Webhooks\Exception\RejectedException` — **401**. The response body is the public message, which defaults to `Invalid signature`. The exception's log message can be specific (`Signature mismatch`, `Signature timestamp outside tolerance`) and is written to the file log only.

Two traits implement `getProtection()` for the schemes you will actually meet. A handler that needs something else returns its own `Protection`.

## Shared secret

`Nails\Webhooks\Traits\SharedSecret` compares a header with `hash_equals`.

```php
namespace App\Webhooks;

use Nails\Webhooks\Delivery;
use Nails\Webhooks\Interfaces\Webhook;
use Nails\Webhooks\Interfaces\Webhook\ProtectedWebhook;
use Nails\Webhooks\Result;
use Nails\Webhooks\Traits\Defaults;
use Nails\Webhooks\Traits\SharedSecret;

class BuildFinished implements Webhook, ProtectedWebhook
{
    use Defaults;
    use SharedSecret;

    public function getLabel(): string
    {
        return 'Build finished';
    }

    protected function secret(Delivery $oDelivery): string
    {
        return (string) appSetting('build_webhook_secret', 'app');
    }

    public function handle(Delivery $oDelivery): Result
    {
        return Result::accepted('Recorded build');
    }
}
```

The default header is `X-Webhook-Token`. Override `secretHeader()` to read a different one.

A query-string token stays off unless `allowsQueryToken()` returns `true`. Query strings land in access logs. Prefer the header.

On a [configurable](instances.md) handler, `secret()` reads the instance:

```php
protected function secret(Delivery $oDelivery): string
{
    return (string) $oDelivery->instance()->secret;
}
```

Admin generates that secret when the instance is created. It is shown on the edit screen and replaced only by **Rotate secret**.

## HMAC signature

`Nails\Webhooks\Traits\SignsPayload` checks an HMAC of the raw body. The handler supplies the secret and the header name.

```php
namespace App\Webhooks;

use Nails\Webhooks\Delivery;
use Nails\Webhooks\Interfaces\Webhook;
use Nails\Webhooks\Interfaces\Webhook\ProtectedWebhook;
use Nails\Webhooks\Result;
use Nails\Webhooks\Traits\Defaults;
use Nails\Webhooks\Traits\SignsPayload;

class SignedCallback implements Webhook, ProtectedWebhook
{
    use Defaults;
    use SignsPayload;

    public function getLabel(): string
    {
        return 'Signed callback';
    }

    protected function secret(Delivery $oDelivery): string
    {
        return (string) appSetting('webhook_signing_secret', 'app');
    }

    protected function signatureHeader(): string
    {
        return 'X-Signature';
    }

    protected function signsTimestamp(): bool
    {
        return true;
    }

    public function handle(Delivery $oDelivery): Result
    {
        return Result::accepted('Signed payload accepted');
    }
}
```

**Plain mode** (`signsTimestamp()` is `false`, the default). The header is the hex HMAC of the raw body. `signatureEncoding()` can return `base64` instead. `signatureAlgorithm()` defaults to `sha256`.

**Timestamp mode** (`signsTimestamp()` returns `true`). The header looks like Stripe's:

```
t=1700000000,v1=<hex>[,v1=<hex>]
```

The signed payload is `{timestamp}.{raw body}`. The timestamp must be within `signatureTolerance()` seconds of now. That defaults to 300 (`Nails\Webhooks\Constants::SIGNATURE_TOLERANCE`). Any matching `v1` passes, so a provider can rotate a secret without a gap.

`Authorization`, `Cookie`, and the signature or secret header are redacted in the file log to `[redacted, N bytes]`. The secret itself is never written.

## Challenge

Some providers call the URL once and expect a specific body back before they will send events. That is still a `Protection`. `verify()` returns `Result::challenge()` for the handshake and `null` (or a rejection) for every later request.

```php
use Nails\Webhooks\Interfaces\Protection;
use Nails\Webhooks\Result;

class SlackHandshake implements Protection
{
    public function verify(Delivery $oDelivery): ?Result
    {
        $oBody = $oDelivery->json();
        if (($oBody->type ?? '') === 'url_verification') {
            return Result::challenge((string) $oBody->challenge);
        }

        // …check the signature, or throw RejectedException…
        return null;
    }

    public function sensitiveHeaders(): array
    {
        return ['X-Slack-Signature'];
    }
}
```

`sensitiveHeaders()` names the headers to redact in the file log. A challenge is an audit row with status `challenged`. It does not count as a successful delivery, and `handle()` is not called.

## Subscribing the URL

Telling the provider "send events here" is a different job from checking the signature. Implement `Nails\Webhooks\Interfaces\Subscribable` when the handler should do that itself:

```php
public function subscribe(Instance $oInstance): void;

public function unsubscribe(Instance $oInstance): void;

public function subscriptionStatus(Instance $oInstance): string;
```

Admin calls `subscribe()` when an enabled instance is created or saved, and again after the token or secret is rotated. It calls `unsubscribe()` when the instance is disabled or deleted. The status string is stored on the instance and shown on the edit screen. An exception becomes the admin error message. The handler owns the HTTP call. There is no generic client that tries to speak every provider's API.

Pasting the URL into the provider's dashboard remains the right approach for a handler that does not implement `Subscribable`.

## Payment notifications

`nails/driver-invoice-stripe` would add `src/Stripe/Webhooks/PaymentNotification.php`. The class is `Nails\Invoice\Driver\Payment\Stripe\Webhooks\PaymentNotification`, and the URL is `/webhooks/nails/driver-invoice-stripe/payment-notification`.

That handler is a singleton: one platform account, no `Configurable` trait, and the signing secret already lives in the driver settings. It uses `SignsPayload` in timestamp mode with the header `Stripe-Signature`. `handle()` switches on `event.type` and completes or refunds the payment. An unknown event type returns `Result::ignored()`.

Stripe retries the same bytes, so the default body hash is enough to ignore a duplicate. Implement `Nails\Webhooks\Interfaces\Idempotent` and return `event.id` only if a retry would not be byte-identical.

A second class in the same driver, this one using `Configurable`, is the Connect case: each [instance](instances.md) stores its own Stripe account and signing secret, and `secret()` reads the instance. It is a separate class, not a flag on the singleton.
