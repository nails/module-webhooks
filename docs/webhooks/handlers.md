---
description: >-
  How a class under src/Webhooks becomes an endpoint, how its URL is derived,
  and what handle() is expected to return.
---

# Handlers

Any instantiable class under `src/Webhooks` that implements `Nails\Webhooks\Interfaces\Webhook` is a handler. That applies to the app (`App\Webhooks\…`) and to every installed module (`Nails\Invoice\Webhooks\…`, `Nails\Invoice\Driver\Payment\Stripe\Webhooks\…`, and so on).

You do not register handlers. On boot, `Nails\Webhooks\Service\Webhook` walks every available component, finds classes in that component's `Webhooks` namespace, and keeps those that implement the interface and can be constructed. The same lookup is how the app finds event listeners, validation rules, and admin permissions.

```php
use Nails\Factory;
use Nails\Webhooks\Constants;
use Nails\Webhooks\Service\Webhook;

/** @var Webhook $oWebhooks */
$oWebhooks = Factory::service('Webhook', Constants::MODULE_SLUG);
```

`services/services.php` registers the service under the name `Webhook`. An app can replace it with `App\Webhooks\Service\Webhook`, the same way other modules allow an override.

The interface, traits, and protection classes live outside `src/Webhooks`, so the scan never treats them as handlers.

## Contract

```php
namespace Nails\Webhooks\Interfaces;

interface Webhook
{
    public function getLabel(): string;

    public function getDescription(): string;

    public function isEnabled(): bool;

    public function handle(Delivery $oDelivery): Result;
}
```

`Nails\Webhooks\Traits\Defaults` supplies `getDescription()` as `''` and `isEnabled()` as `true`. A handler uses the trait and implements `getLabel()` and `handle()`.

`isEnabled()` returning `false` hides the endpoint. The request is a **404** and no audit row is written. Use that for a handler you want to ship before it should receive traffic.

## Slug

The handler does not choose its slug. The catalogue derives it:

`{component slug}/{kebab-case path under Webhooks}`

The component slug is the Composer package name. The app's slug is `app`. Each segment under `Webhooks` is kebab-cased on camel-case boundaries, so `PaymentNotification` becomes `payment-notification` and `PostToChannel` becomes `post-to-channel`.

| Class | URL |
|---|---|
| `\App\Webhooks\BuildFinished` | `/webhooks/app/build-finished` |
| `\App\Webhooks\PostToChannel` | `/webhooks/app/post-to-channel` |
| `\Nails\Invoice\Webhooks\PaymentNotification` | `/webhooks/nails/module-invoice/payment-notification` |
| `\Nails\Invoice\Driver\Payment\Stripe\Webhooks\PaymentNotification` | `/webhooks/nails/driver-invoice-stripe/payment-notification` |
| `\Nails\Invoice\Webhooks\Stripe\PaymentNotification` | `/webhooks/nails/module-invoice/stripe/payment-notification` |

A nested folder stays in the path, so two classes with the same short name in one package still differ.

Two components that share a package name and the same class path produce the same slug. Discovery throws rather than picking a winner. There is no `getSlug()` and no override: renaming the class is how the URL changes.

There is one catch-all route, `webhooks/(.+)`. The last segment is an instance token only when it is 64 hex characters **and** the remainder is a [configurable](instances.md) definition. A token stuck on the end of a singleton URL is a **404**.

## Delivery

`handle()` receives a `Nails\Webhooks\Delivery`.

| Member | What it is |
|---|---|
| `$oDelivery->uuid` | Id for this attempt. The same value prefixes every line in the file log. |
| `$oDelivery->rawBody` | The body, read once from `php://input`. |
| `$oDelivery->json()` | The body decoded as an object, or an empty object when it is not JSON. |
| `$oDelivery->method` | `GET` or `POST`. |
| `$oDelivery->header($sName)` | A header, matched without caring about case. |
| `$oDelivery->query` | The query string. |
| `$oDelivery->slug` | The derived slug. |
| `$oDelivery->instance()` | The [instance](instances.md) row, or `null` for a singleton. |
| `$oDelivery->log($sLine)` | Writes a line to the file log, prefixed with the uuid. |

`handle()` does not write the HTTP response. It returns a `Result`.

## Results

| Factory | Meaning | HTTP |
|---|---|---|
| `Result::accepted($sSummary)` | Handled | 200 |
| `Result::ignored($sSummary)` | Recognised and deliberately skipped | 200 |
| `Result::failed($sSummary)` | Try again later | 500 |
| `Result::challenge($sBody, $iStatus, $aHeaders)` | Handshake. `handle()` is not called. Used from [protection](protection.md), not from `handle()`. | as given |

An exception from `handle()` is a failure: **500**, with the exception message as the summary and the trace in the file log. The provider retries.

`ignored` is **200** so a provider does not retry an event type the handler does not care about.

```php
public function handle(Delivery $oDelivery): Result
{
    $sType = (string) ($oDelivery->json()->type ?? '');

    return match ($sType) {
        'payment_intent.succeeded' => Result::accepted('Payment completed'),
        'ping'                     => Result::ignored('Ping'),
        default                    => Result::ignored('Unhandled ' . $sType),
    };
}
```

The summary is stored on the audit row and shown in admin. Keep it short. It is truncated at 500 characters.

{% hint style="info" %}
Handling is inline. A provider such as Stripe gives the endpoint about 20 seconds. If the work might take longer, accept the delivery, queue the work yourself, and return. This module does not queue it for you.
{% endhint %}
