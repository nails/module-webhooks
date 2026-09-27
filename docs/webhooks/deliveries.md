---
description: >-
  How duplicate deliveries are ignored, where the file log and the audit row
  live, and what the admin screens show.
---

# Deliveries

Every request that resolves to an enabled handler becomes one audit row, including a bad signature, a challenge, and a repeat. A request that 404s — unknown slug, unknown token, disabled handler or instance — is not recorded.

## Duplicates

Retries are the normal case. A provider sends the same body again when it does not get a 2xx in time, or when it is not sure the first call landed.

Unless the handler opts out, ingress builds an idempotency key **before** verification:

1. A class that uses `Nails\Webhooks\Traits\AllowsDuplicates` has no key. `handle()` runs on every verified request.
2. A class that implements `Nails\Webhooks\Interfaces\Idempotent` can return its own key from `getIdempotencyKey(Delivery)`. An empty return falls through to the body hash. `AllowsDuplicates` wins if a class has both.
3. Otherwise the key is `sha256` of the raw body.

The key is scoped to the instance id, or to `definition:{slug}` for a singleton. Two instances of the same handler do not share a duplicate check.

A previous row with status `accepted` or `ignored` and the same scope and key means this attempt is a duplicate:

- a new audit row is written with status `ignored` and summary `Duplicate delivery {original uuid}`
- that row stores a null idempotency key, so it does not take the unique slot
- the response is **200**
- `handle()` is not called

`failed`, `rejected`, and `challenged` do not count. A retry after an error, or after a bad signature, still runs. If two identical requests pass the lookup at the same moment, the insert that loses the unique key is recorded as ignored and answered **200**.

```php
use Nails\Webhooks\Delivery;
use Nails\Webhooks\Interfaces\Idempotent;

class PaymentNotification implements Idempotent, Webhook, ProtectedWebhook
{
    public function getIdempotencyKey(Delivery $oDelivery): ?string
    {
        $sId = $oDelivery->json()->id ?? null;

        return is_string($sId) && $sId !== '' ? $sId : null;
    }

    // secret(), signatureHeader(), signsTimestamp(), and handle() stay as they are.
}
```

Return `null` to keep the body hash. Stripe's retries are byte-identical, so the hash is enough and this interface is optional there. Use it when the body is not a stable identity — a changing attempt id in the payload, for example.

Definitions in admin show dedupe as **On**, or **Off** when the class uses `AllowsDuplicates`.

## File log

Verbose tracing goes to `application/logs/webhooks/log-Y-m-d.php`, through `Nails\Common\Factory\Logger`. The file starts with the usual `<?php die('Unauthorised'); ?>` guard. Each line is prefixed with the delivery uuid.

The log contains the method, path, and IP; the headers; the raw body; each verification step; anything the handler writes with `$oDelivery->log()`; the exception trace when there is one; and the final status.

`Authorization`, `Cookie`, and the signature or secret header are stored as `[redacted, N bytes]`. The secret value is never written.

{% hint style="warning" %}
The file log contains the payload. Browsing the audit table and opening a payload are separate permissions.
{% endhint %}

`module-housekeeping` already archives `application/logs`. Audit rows are kept. This module does not add its own housekeeping routine.

## Audit row

The database stores the outcome, not the transcript. The raw body is not in MySQL.

| Column | Notes |
|---|---|
| `uuid` | Also the file-log prefix. |
| `definition_class`, `definition_slug` | Which handler. |
| `instance_id` | Null for a singleton. Set to null if the instance is later deleted. |
| `scope` | Instance id, or `definition:{slug}`. |
| `status` | `accepted`, `ignored`, `failed`, `rejected`, or `challenged`. |
| `http_status` | What the provider was sent. |
| `idempotency_key` | The body hash or the handler's key. Null on a duplicate row and on a handler that allows duplicates. |
| `summary` | Short text from the `Result`, the rejection, or `Duplicate delivery {uuid}`. |
| `duration_ms` | |
| `log_file` | The day's file, so admin can open the matching lines. |
| `created` | |

A generated `success_key` is the idempotency key when the status is `accepted` or `ignored`, and null otherwise. The unique key is `(scope, success_key)`. Many failed or rejected rows can share a key. A second success cannot.

## Admin

**Admin → Webhooks → Definitions** is read only. It is built from the catalogue, not from a table. Columns are label, slug, component, flavour (Simple or Protected), protection (shared secret, signature, custom, or none), shape (Singleton or Configurable), dedupe, and the URL. Configurable handlers show "Per instance" instead of a URL. **Deliveries** opens the audit list with that slug in the search box.

**Admin → Webhooks → Deliveries** is the audit list, newest first. Columns are received, definition, status, HTTP status, summary, and duration. The status filter offers accepted, ignored, failed, challenged, and rejected. The search box matches the slug, summary, status, and uuid. There is no create, edit, or delete.

**Log** on a row shows the audit fields and the file-log lines for that uuid.

| Permission | Class |
|---|---|
| Can browse webhook definitions | `Nails\Webhooks\Admin\Permission\Definition\Browse` |
| Can manage webhook instances | `Nails\Webhooks\Admin\Permission\Instance\Manage` |
| Can browse webhook deliveries | `Nails\Webhooks\Admin\Permission\Delivery\Browse` |
| Can view a webhook delivery, including the file log | `Nails\Webhooks\Admin\Permission\Delivery\View` |

## Events

After the audit row is written, ingress triggers `delivery.completed` with the delivery id. Business logic stays in `handle()`. The event is for something else that wants to know a delivery was recorded.

The constant is `Nails\Webhooks\Events::DELIVERY_COMPLETED`. Subscribe the same way as any other [event](../../core-services/event.md):

```php
namespace App\Event\Listener;

use Nails\Common\Events\Subscription;
use Nails\Webhooks\Events;

class DeliveryCompleted extends Subscription
{
    public function __construct()
    {
        $this
            ->setEvent(Events::DELIVERY_COMPLETED)
            ->setNamespace(Events::getEventNamespace())
            ->setCallback([$this, 'execute']);
    }

    public function execute(int $iDeliveryId): void
    {
        // The audit row $iDeliveryId has been written.
    }
}
```
