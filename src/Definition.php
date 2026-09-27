<?php

namespace Nails\Webhooks;

use Nails\Webhooks\Interfaces\Webhook;
use Nails\Webhooks\Interfaces\Webhook\ProtectedWebhook;
use Nails\Webhooks\Traits\AllowsDuplicates;
use Nails\Webhooks\Traits\Configurable;
use Nails\Webhooks\Traits\SharedSecret;
use Nails\Webhooks\Traits\SignsPayload;

class Definition
{
    public function __construct(
        public readonly string $class,
        public readonly string $slug,
        public readonly string $componentSlug,
        public readonly string $componentName,
        private readonly Webhook $oHandler,
    ) {
    }

    public function handler(): Webhook
    {
        return $this->oHandler;
    }

    public function isProtected(): bool
    {
        return $this->oHandler instanceof ProtectedWebhook;
    }

    public function isConfigurable(): bool
    {
        return self::uses($this->oHandler, Configurable::class);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function configFields(): array
    {
        if (!$this->isConfigurable() || !method_exists($this->oHandler, 'getConfigFields')) {
            return [];
        }

        $aFields = $this->oHandler->getConfigFields();

        return is_array($aFields) ? $aFields : [];
    }

    public function allowsDuplicates(): bool
    {
        return self::uses($this->oHandler, AllowsDuplicates::class);
    }

    public function protection(): string
    {
        if (!$this->isProtected()) {
            return 'none';
        }
        if (self::uses($this->oHandler, SharedSecret::class)) {
            return 'shared secret';
        }
        if (self::uses($this->oHandler, SignsPayload::class)) {
            return 'signature';
        }

        return 'custom';
    }

    public function flavour(): string
    {
        return $this->isProtected() ? 'Protected' : 'Simple';
    }

    public function url(?string $sToken = null): string
    {
        $sPath = 'webhooks/' . $this->slug;
        if ($sToken !== null && $sToken !== '') {
            $sPath .= '/' . $sToken;
        }

        return function_exists('siteUrl') ? siteUrl($sPath) : '/' . $sPath;
    }

    private static function uses(object $oHandler, string $sTrait): bool
    {
        $aTraits = class_uses($oHandler) ?: [];
        foreach (class_parents($oHandler) ?: [] as $sParent) {
            $aTraits += class_uses($sParent) ?: [];
        }

        $aPending = $aTraits;
        while ($aPending !== []) {
            $sUsed = array_key_first($aPending);
            unset($aPending[$sUsed]);
            foreach (class_uses($sUsed) ?: [] as $sNested) {
                if (!isset($aTraits[$sNested])) {
                    $aTraits[$sNested] = $sNested;
                    $aPending[$sNested] = $sNested;
                }
            }
        }

        return in_array($sTrait, $aTraits, true);
    }
}
