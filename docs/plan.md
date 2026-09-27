# Webhooks module

A formal way for any Nails component to expose an HTTP endpoint that receives a callback, verifies it, and records what happened. The class owns the behaviour. Configuration, when someone needs their own copy, lives in the database.

`module-invoice` is the reference consumer: a payment driver receives an asynchronous notification, checks the signature, and marks the payment complete. That handler is not part of this module.

## Discovery

Handlers live in `src/Webhooks` on any component, including the app. A class is a webhook when it implements `Nails\Webhooks\Interfaces\Webhook` and can be constructed.

This is the same lookup event listeners, validation rules, and admin permissions already use:

```php
foreach (Components::available() as $oComponent) {
    $aClasses = $oComponent
        ->findClasses('Webhooks')
        ->whichImplement(\Nails\Webhooks\Interfaces\Webhook::class)
        ->whichCanBeInstantiated();
}
```

`ClassCollection` also has `whichUse()`, which is how configurable handlers are picked out.

`Nails\Webhooks\Service\Webhook` performs that scan once and caches it. Callers ask that service for a slug; they do not scan themselves.

```php
/** @var \Nails\Webhooks\Service\Webhook $oWebhookService */
$oWebhookService = Factory::service('Webhook', Constants::MODULE_SLUG);
```

The service is named `Webhook`, same as `PaymentDriver` and `Event`: the name is the thing it looks after. `services/services.php` registers it under that key.

`composer.json` `extra.nails` has no `namespace` today. `findClasses()` and admin controller discovery both read that value, so the first change is:

```json
"namespace": "Nails\\Webhooks\\"
```

The interface, traits, and protection classes stay outside `src/Webhooks`, so the scan never treats them as handlers.

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

`Nails\Webhooks\Traits\Defaults` supplies `getDescription()` as `''` and `isEnabled()` as `true`. A handler uses the trait and implements label and `handle()`.

## Slug

The handler does not choose its slug. `Service\Webhook` derives it:

`{component slug}/{kebab-case path under Webhooks}`

The component slug is the composer package name (`$oComponent->slug`), already `{vendor}/{package}`. Each segment under `Webhooks` is kebab-cased on camel-case boundaries.

| Class | Slug |
|---|---|
| `\Nails\Invoice\Webhooks\PaymentNotification` | `nails/module-invoice/payment-notification` |
| `\Nails\Invoice\Driver\Payment\Stripe\Webhooks\PaymentNotification` | `nails/driver-invoice-stripe/payment-notification` |
| `\Nails\Invoice\Webhooks\Stripe\PaymentNotification` | `nails/module-invoice/stripe/payment-notification` |
| `\App\Webhooks\PostToChannel` | `app/post-to-channel` |

A nested folder stays in the path, so two classes with the same short name in one package still differ.

A method such as `getSlug()` would be a second global namespace. Two modules can both reasonably be called `payment-notification`, and a tie-break would hide one of them. The derived slug only collides when two components share a package name. Discovery throws if that happens, rather than picking a winner.

The URL is longer than a hand-written slug. It is copied from the admin screen into the provider once. Renaming or moving the class changes the URL. That is the same identity change as renaming the class, and there is no override that lets the two drift apart.

`handle()` returns a `Result`. It does not write the HTTP response.

| Factory | Meaning | HTTP |
|---|---|---|
| `Result::accepted($sSummary)` | Handled | 200 |
| `Result::ignored($sSummary)` | Recognised and deliberately skipped | 200 |
| `Result::failed($sSummary)` | Try again later | 500 |
| `Result::challenge($sBody, $iStatus, $aHeaders)` | Handshake response, `handle()` is not called | as given |

An exception from `handle()` is a failure: 500, so the provider retries. `ignored` is 200 so a provider does not retry an event type the handler does not care about.

`Delivery` carries the raw body, method, headers, query string, the handler, the instance when there is one, and `log()`. `json()` decodes the body. The body is read once from `php://input` before any parsing.

## Flavours

Flavour is an interface the handler adds, not a string on the base class. Admin reads it with `classImplements` / `classUses`.

### Simple

Implements `Webhook` only. The ingress calls `handle()` after resolving the URL. Use this when the sender cannot sign, or when the handler is a private integration whose URL is the only secret. The slug itself is not a secret.

### Protected

Implements `Nails\Webhooks\Interfaces\Webhook\ProtectedWebhook`:

```php
interface ProtectedWebhook extends Webhook
{
    public function getProtection(Delivery $oDelivery): Protection;
}
```

`Protection::verify(Delivery $oDelivery): ?Result`

- `null` means the request is genuine and handling continues.
- a `Result` short-circuits. A challenge result is returned as-is. Anything else is a rejection.
- `Nails\Webhooks\Exception\RejectedException` is the failure path. The public message is generic (`Invalid signature`). The log message can be specific. Response is 401.

Two traits cover the common cases. Each implements `getProtection()` and leaves the details to the handler.

`Nails\Webhooks\Traits\SharedSecret`

- Compares a header value with `hash_equals`.
- Default header is `X-Webhook-Token`.
- The handler implements `secret(Delivery $oDelivery): string`.
- Query-string tokens stay off unless the handler opts in. Query strings land in access logs.

`Nails\Webhooks\Traits\SignsPayload`

- HMAC of the raw body.
- Default algorithm `sha256`, header supplied by the handler.
- Plain mode: the header is hex (or base64, chosen by the handler) of `hash_hmac(algo, rawBody, secret)`.
- Timestamp mode: header format `t=<unix>,v1=<hex>`, signed payload `"{$timestamp}.{$rawBody}"`, default tolerance 300 seconds. Multiple `v1` values are accepted so a provider can rotate a secret without a gap. This is the Stripe shape.
- The handler implements `secret(Delivery $oDelivery): string` and `signatureHeader(): string`.

A handler that needs a scheme those two cannot express returns its own `Protection` from `getProtection()`. The Stripe driver can start on `SignsPayload` in timestamp mode.

### Subscribe

Two different mechanisms travel under this name. They stay separate so a signature check does not grow a second responsibility.

**Inbound challenge.** The provider calls the URL and expects a specific body back (Slack `url_verification`, Meta `hub.challenge`). That is a `Protection` which returns `Result::challenge()` for the handshake request and verifies the signature for every other request. It ships in the first build because the ingress has to understand a short-circuit anyway.

**Outbound registration.** On save, the app tells the provider "send events here" (Stripe's webhook endpoint API, Graph subscriptions). That is `Nails\Webhooks\Interfaces\Subscribable`:

```php
public function subscribe(Instance $oInstance): void;
public function unsubscribe(Instance $oInstance): void;
public function subscriptionStatus(Instance $oInstance): string;
```

The admin instance screen shows subscribe, unsubscribe, and status when the definition implements this. There is no generic client that tries to speak every provider's API. The handler owns the HTTP call. Pasting the URL into the provider's dashboard remains valid for handlers that do not implement `Subscribable`.

## Configurable instances

A definition is always a file. Some definitions are also a template: the decode-and-act logic is shared, and each user supplies their own channel, secret, and label.

Using `Nails\Webhooks\Traits\Configurable` marks that template. `Service\Webhook` finds them with `whichUse(Configurable::class)`.

```php
trait Configurable
{
    /** @return array<string, mixed> field key => Form helper / model field definition */
    abstract public function getConfigFields(): array;

    public function config(Delivery $oDelivery, string $sKey, mixed $mDefault = null): mixed
    {
        return $oDelivery->instance()->config($sKey, $mDefault);
    }
}
```

A "post to channel" handler looks like this:

```php
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
        // decode $oDelivery->json() and post it to $sChannel
        return Result::accepted('Posted to ' . $sChannel);
    }
}
```

What is always on the instance row, outside `getConfigFields()`:

- label
- enabled
- owner (`created_by`)
- secret, when the definition is protected
- the URL token

What stays on the class:

- how to read the payload
- what to do with it
- which protection scheme
- the field schema

A definition without the trait is a singleton. One URL, no instance row. Its secret comes from wherever that component already keeps secrets (the Stripe driver settings, for example). `secret(Delivery)` ignores the instance.

## URLs

| Kind | URL |
|---|---|
| Singleton | `/webhooks/nails/module-invoice/payment-notification` |
| Instance | `/webhooks/app/post-to-channel/{token}` |

The token is `bin2hex(random_bytes(32))`, stored on the instance, and shown in admin so it can be copied to the provider. Regenerating it is an explicit action. Unknown slug, unknown token, and disabled handler or instance all answer 404.

The slug contains slashes, so there is one catch-all route:

```php
'webhooks/(.+)' => 'webhooks/ingress/index/$1',
```

`Ingress` splits that path. When the last segment is 64 hex characters and the remainder is a configurable definition, that segment is the instance token. Otherwise the whole path is the slug.

The public controller `webhooks/controllers/Ingress.php` reads the request and calls `Nails\Webhooks\Service\Ingress`. GET and POST are accepted. Anything else is 405. CSRF protection is off in Nails by default; an app that enables it excludes `webhooks/.*`.

## Ingress

`Service\Ingress::receive(string $sPath)` parses the slug and optional token, then:

1. Resolve the definition. 404 when missing or `isEnabled()` is false.
2. For a configurable definition, resolve the instance by token. 404 when missing, disabled, or soft-deleted. A configurable definition with no token is also 404.
3. A singleton URL that includes a token is 404.
4. Read the raw body once. Open the file log.
5. When the handler is protected, `verify()`. A challenge result is logged and returned. A rejection is an audit row of `rejected` and 401. `handle()` is not called.
6. Unless the handler uses `AllowsDuplicates`, resolve the idempotency key (below). When a previous `accepted` or `ignored` row exists for this scope and key, write a new audit row of `ignored` with summary `Duplicate delivery`, return 200, and do not call `handle()`.
7. Call `handle()`.
8. Write the audit row from the `Result`. If that insert loses a race on the success unique key, write `ignored` / `Duplicate delivery` and return 200. A handler using `AllowsDuplicates` stores a `NULL` key, so this race does not apply.
9. On an exception, log the trace to the file, write status `failed`, return 500.

Duplicates are handled for every webhook. The default key is `sha256` of the raw body, so a provider retry of the same request becomes `Result::ignored()` and `handle()` runs once. `failed` and `rejected` do not count: a retry after an error or a bad signature still runs. `challenged` does not count either.

Scope is the instance id, or `definition:{slug}` for a singleton. The column exists because a MySQL unique index does not treat `NULL` instance ids as equal.

`Nails\Webhooks\Interfaces\Idempotent::getIdempotencyKey(Delivery $oDelivery): ?string` replaces the body hash when the body is not a stable identity (a changing attempt id in the payload, for example). A null return keeps the body hash. Stripe retries are byte-identical, so `event.id` is optional there.

`Nails\Webhooks\Traits\AllowsDuplicates` turns dedupe off for that handler. `Service\Webhook` sees it with `whichUse()`. Ingress skips the lookup, stores `idempotency_key` as `NULL`, and calls `handle()` on every verified request. The trait wins if the class also implements `Idempotent`.

Handling is inline. A handler that may exceed the provider's timeout (Stripe gives about 20 seconds) accepts the `Result`, queues its own work, and returns. This module does not grow a queue dependency for that.

After the audit row is written, trigger `delivery.completed` in `Nails\Webhooks\Events` with the delivery id. Business logic stays in `handle()`.

## Logging

Two stores, on purpose.

**File, verbose.** `Nails\Common\Factory\Logger`, directory `application/logs/webhooks/`, one file per day, each line prefixed with the delivery UUID. The file starts with the usual `<?php die('Unauthorised'); ?>` guard. Contents:

- time, definition, instance, method, IP
- headers, with `Authorization` and the signing header redacted to a length only
- the raw body
- each verification step
- lines the handler writes through `$oDelivery->log()`
- the exception trace, when there is one
- the final status

The secret value is never written. `Model\Delivery` stores the log path.

**Database, audit.** One row per request that reached a known handler, including rejections and challenges. Columns are the outcome, not the transcript: status, HTTP status, summary (short, safe to show in admin), duration, idempotency key, definition, instance. The raw body is not stored.

Statuses: `rejected`, `challenged`, `accepted`, `ignored`, `failed`.

`module-housekeeping` already archives `application/logs`. A routine in this module is optional and only worth adding if daily files need a shorter life than the app logs. Audit rows stay.

## Tables

Migration `Nails\Webhooks\Database\Migration\Migration1`, using `Nails\Common\Interfaces\Database\Migration` and `Nails\Common\Traits\Database\Migration`. Prefix placeholder `{{NAILS_DB_PREFIX}}`.

`webhook_instance`

| Column | Notes |
|---|---|
| `id` | |
| `definition_class` | FQCN |
| `definition_slug` | denormalised for the URL and admin filters |
| `token` | 64 hex chars, unique |
| `label` | |
| `is_enabled` | |
| `secret` | nullable; set when the definition is protected |
| `config` | JSON object of `getConfigFields()` values |
| `subscription_status` | nullable; written by `Subscribable` handlers |
| `is_deleted`, `created`, `created_by`, `modified`, `modified_by` | standard model columns |

`webhook_delivery`

| Column | Notes |
|---|---|
| `id` | |
| `uuid` | char(36), unique, also the file-log prefix |
| `definition_class`, `definition_slug` | |
| `instance_id` | nullable, FK, `ON DELETE SET NULL` |
| `scope` | instance id or `definition:{slug}` |
| `status` | |
| `http_status` | |
| `idempotency_key` | sha256 of the body, or the handler's key |
| `success_key` | generated: `idempotency_key` when status is `accepted` or `ignored`, otherwise `NULL` |
| `summary` | varchar(500) |
| `duration_ms` | |
| `log_file` | |
| `created` | |

Unique `(scope, success_key)`. MySQL allows many `NULL`s, so several `failed` or `rejected` rows can share a key, and a second `accepted` or `ignored` insert conflicts. Indexes on `(definition_slug, created)`, `(instance_id, created)`, and `(scope, idempotency_key)`.

Models `Nails\Webhooks\Model\Instance` and `Model\Delivery` extend `Nails\Common\Model\Base`. `config` is encoded and decoded in the model, the same way invoice treats `callback_data`. Resources `Resource\Instance` and `Resource\Delivery` sit beside them. `Instance::config($sKey, $mDefault)` reads the decoded object.

`Delivery` rows are insert-only from the ingress. The model does not offer an update path to admin.

## Admin

Controllers live in `src/Admin/Controller` so `Nails\Admin\Service\Controller` discovers them (`findClasses('Admin\\Controller')` plus `Nails\Admin\Interfaces\Controller`). Permissions live in `src/Admin/Permission`.

Sidebar group **Webhooks**.

**Definitions** (`Admin\Controller\Definition`). Read only. Built from `Service\Webhook`, not a table, so it does not extend `DefaultController`. Columns: label, slug, component, flavour (Simple / Protected), protection (shared secret, signature, custom, or none), shape (singleton / configurable), dedupe (on, or off when the class uses `AllowsDuplicates`), URL for singletons. Row action opens deliveries filtered to that slug. No create or delete.

**Instances** (`Admin\Controller\Instance`). Custom controller on `Model\Instance`, using the admin base and `announce()`. `DefaultController` is a poor fit because the form fields depend on which definition is selected.

- Create lists only definitions that use `Configurable`.
- Fields: definition, label, enabled, the definition's `getConfigFields()`, and secret when the definition is protected.
- Secret is generated on create (`random_bytes`), shown so it can be copied, and replaced only by an explicit rotate action.
- The URL is read-only.
- Saving a `Subscribable` definition calls `subscribe()` after a successful create or a token/secret rotation, and `unsubscribe()` on disable or delete.
- Edit keeps the definition fixed. Changing behaviour means a new instance.

**Deliveries** (`Admin\Controller\Delivery` extends `DefaultController`). Read only: `CONFIG_CAN_CREATE`, `EDIT`, `DELETE`, `RESTORE`, `DESTROY`, `COPY` are false. Sort by `created` descending. Filters for definition, instance, and status. The view screen shows the audit columns and the file-log lines for that UUID.

Permissions, group "Webhooks":

| Class | Label |
|---|---|
| `Admin\Permission\Definition\Browse` | Can browse webhook definitions |
| `Admin\Permission\Instance\Manage` | Can manage webhook instances |
| `Admin\Permission\Delivery\Browse` | Can browse webhook deliveries |
| `Admin\Permission\Delivery\View` | Can view a webhook delivery, including the file log |

View is separate from browse because the file log contains payloads.

No component settings screen. Per-user configuration is the instance row. Module-level knobs (HMAC tolerance default) can be constants until something needs to change them per app.

## Invoice

This module does not require `nails/module-invoice`. The stripe driver (`nails/driver-invoice-stripe`, component namespace `Nails\Invoice\Driver\Payment\Stripe\`) would add `src/Stripe/Webhooks/PaymentNotification.php`:

- slug `nails/driver-invoice-stripe/payment-notification`, derived from the driver package and the class name
- singleton, no `Configurable` (one platform Stripe account; the signing secret already belongs in the driver settings)
- `ProtectedWebhook` + `SignsPayload` in timestamp mode, header `Stripe-Signature`
- duplicate Stripe retries are ignored from the body hash; `Idempotent` with key `event.id` only if a retry would not be byte-identical
- `handle()` switches on `event.type` (`payment_intent.succeeded`, `charge.refunded`, and the rest) and calls the existing payment complete / refund path
- unknown event types return `Result::ignored()`
- `Subscribable` is a later option if the driver should register the endpoint through Stripe's API; pasting `/webhooks/nails/driver-invoice-stripe/payment-notification` into the Stripe dashboard is enough for the first version

A second, configurable handler in the same driver would be the "connect" case: each instance stores its own Stripe account and signing secret. Same `handle()`, `secret()` reads the instance. That handler uses `Configurable`. It is a separate class, not a mode flag on the singleton.

## Layout

```
src/Constants.php
src/Events.php
src/Routes.php
src/Interfaces/Webhook.php
src/Interfaces/Webhook/ProtectedWebhook.php
src/Interfaces/Protection.php
src/Interfaces/Idempotent.php
src/Interfaces/Subscribable.php
src/Traits/Defaults.php
src/Traits/Configurable.php
src/Traits/SharedSecret.php
src/Traits/SignsPayload.php
src/Traits/AllowsDuplicates.php
src/Protection/HmacSignature.php
src/Protection/SharedSecretHeader.php
src/Delivery.php
src/Result.php
src/Exception/RejectedException.php
src/Exception/UnknownWebhookException.php
src/Service/Webhook.php
src/Service/Ingress.php
src/Service/Log.php
src/Model/Instance.php
src/Model/Delivery.php
src/Resource/Instance.php
src/Resource/Delivery.php
src/Database/Migration/Migration1.php
src/Admin/Controller/Definition.php
src/Admin/Controller/Instance.php
src/Admin/Controller/Delivery.php
src/Admin/Permission/Definition/Browse.php
src/Admin/Permission/Instance/Manage.php
src/Admin/Permission/Delivery/Browse.php
src/Admin/Permission/Delivery/View.php
webhooks/controllers/Ingress.php
services/services.php
```

`services/services.php` registers `Webhook`, `Ingress`, `Log`, both models, and both resources, with the usual `\App\Webhooks\...` override check used by invoice. Callers load the catalogue with `Factory::service('Webhook', Constants::MODULE_SLUG)`.

## Dependencies

`composer.json` currently requires `nails/module-cdn`. Nothing in this module needs the CDN. Replace it with `nails/module-admin`, which provides `DefaultController`, nav, and permission discovery. `nails/common` stays. `module-invoice`, `module-queue`, and `module-housekeeping` stay out.

Add `extra.nails.namespace`. Add the phpstan config the `analyse` script already points at (`.phpstan/config.neon`, level 0, same shape as invoice) when the `src` tree exists.

## Tests

Pure unit tests, no database:

- `HmacSignature` accepts a good signature, rejects a bad one, rejects an old timestamp, and accepts either `v1` during rotation
- `SharedSecretHeader` uses a non-short-circuit compare (`hash_equals` is covered by a mismatch and a match)
- challenge `Protection` returns a `Result` and does not look like a rejection
- `Ingress` with a fake `Webhook` service and fake models: unknown slug 404, bad signature 401 and no `handle()`, accepted 200 and an audit insert, thrown exception 500, a second identical body after `accepted` is `ignored` and does not call `handle()`, a second identical body after `failed` does call `handle()`, a handler using `AllowsDuplicates` calls `handle()` on every identical body
- `Webhook` slug derivation, given an explicit component and class list so the test does not need a booted app: `PaymentNotification` on `nails/module-invoice` becomes `nails/module-invoice/payment-notification`; `Webhooks\Stripe\PaymentNotification` keeps the extra segment; an app class becomes `app/post-to-channel`. A duplicate derived slug throws. `discover()` remains the path that calls `Components::available()`

Fixtures live under `tests/Fixture`, not `src/Webhooks`, so a real install never exposes them.

## Build order

1. Composer: namespace, swap `module-cdn` for `module-admin`, phpstan config.
2. Interfaces, traits, `Delivery`, `Result`, exceptions.
3. `HmacSignature` and `SharedSecretHeader`, with tests.
4. `Service\Webhook`, with the slug derivation test.
5. Migration, models, resources, `services.php`.
6. `Log` and `Ingress`, with the ingress tests. Public controller and routes.
7. Admin definitions, instances, deliveries, permissions.
8. README: how to add a handler, the singleton Stripe sketch, the configurable "post to channel" sketch.

## Left out

- The Stripe handler itself (belongs in `driver-invoice-stripe`).
- A generic outbound subscribe HTTP client.
- Storing raw payloads in MySQL.
- Receiving on a queue inside this module.
- Rate limiting.
