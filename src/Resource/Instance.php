<?php

namespace Nails\Webhooks\Resource;

use Nails\Common\Resource\Entity;
use stdClass;

class Instance extends Entity
{
    public ?string $definition_class = null;

    public ?string $definition_slug = null;

    public ?string $token = null;

    public ?string $label = null;

    public ?string $secret = null;

    public mixed $config = null;

    public int|string|null $is_enabled = null;

    public ?string $subscription_status = null;

    public function config(string $sKey, mixed $mDefault = null): mixed
    {
        $mConfig = $this->config;
        if (is_string($mConfig)) {
            $mConfig = json_decode($mConfig);
        }

        if ($mConfig instanceof stdClass && isset($mConfig->{$sKey})) {
            return $mConfig->{$sKey};
        }

        if (is_array($mConfig) && array_key_exists($sKey, $mConfig)) {
            return $mConfig[$sKey];
        }

        return $mDefault;
    }

    /**
     * @return array<string, mixed>
     */
    public function configArray(): array
    {
        $mConfig = $this->config;
        if (is_string($mConfig)) {
            $mConfig = json_decode($mConfig, true);
        }
        if ($mConfig instanceof stdClass) {
            $mConfig = (array) $mConfig;
        }

        return is_array($mConfig) ? $mConfig : [];
    }

    public function isEnabled(): bool
    {
        return (int) $this->is_enabled === 1;
    }
}
