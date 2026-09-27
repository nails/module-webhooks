---
description: >-
  Give one webhook class many copies, each with its own label, secret, config,
  and URL token.
---

# Instances

A definition is always a file. Some definitions are also a template: the decode-and-act logic is shared, and each copy supplies its own channel, owner, and secret.

`Nails\Webhooks\Traits\Configurable` marks that template.

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

`getConfigFields()` is a map of field key to a small definition. `label` is the admin label. A `rules` string that contains `required` makes the create and edit forms reject an empty value.

Inside `handle()`, `$this->config($oDelivery, 'channel')` reads that field from the instance that received the request. `$oDelivery->instance()->config('channel')` is the same value.

## What lives where

On the class:

- how to read the payload
- what to do with it
- which protection scheme
- the field schema

On the instance row:

| Column | Notes |
|---|---|
| `label` | The name shown in admin. Required. |
| `is_enabled` | A disabled instance answers **404**. |
| `created_by` | The admin user who created it. |
| `secret` | Set when the definition is protected. Generated with `random_bytes`, shown so it can be copied, replaced only by **Rotate secret**. |
| `config` | JSON of the `getConfigFields()` values. |
| `token` | 64 hex characters. The last segment of the URL. **Rotate URL token** issues a new one; the provider must be updated. |
| `subscription_status` | Written when the handler implements [Subscribable](protection.md#subscribing-the-url). |

A definition without `Configurable` is a singleton. One URL, no instance row. Its secret comes from wherever that component already keeps secrets.

## URL

```
/webhooks/app/post-to-channel/{token}
```

The token is required. These all answer **404**, and none of them write an audit row:

- the slug with no token
- a token that does not match an instance
- a token whose instance belongs to a different definition
- a disabled instance
- a singleton URL that has a token stuck on the end

Unknown and disabled definitions behave the same way.

## Admin

**Admin → Webhooks → Instances** lists every instance. Creating one starts by choosing a configurable definition. The form then shows the label, the enabled checkbox, and that definition's fields. The definition cannot be changed later. A different behaviour is a new instance.

The edit screen shows the URL and, for a protected definition, the secret. Rotate token, rotate secret, and delete are separate actions. Saving an enabled [subscribable](protection.md#subscribing-the-url) handler calls `subscribe()`. Disabling or deleting calls `unsubscribe()`.

The permission is **Can manage webhook instances** (`Nails\Webhooks\Admin\Permission\Instance\Manage`).
