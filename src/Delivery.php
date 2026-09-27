<?php

namespace Nails\Webhooks;

use Nails\Webhooks\Interfaces\Webhook;
use Nails\Webhooks\Resource\Instance;

class Delivery
{
    /**
     * @param array<string, string> $aHeaders
     * @param array<string, mixed>  $aQuery
     * @param callable(string):void|null $cLog
     */
    public function __construct(
        public readonly string $uuid,
        public readonly Webhook $webhook,
        private readonly ?Instance $oInstance,
        public readonly string $rawBody,
        public readonly string $method,
        public readonly array $headers,
        public readonly array $query,
        public readonly string $slug,
        private $cLog = null,
    ) {
    }

    public function instance(): ?Instance
    {
        return $this->oInstance;
    }

    public function json(): object
    {
        $mDecoded = json_decode($this->rawBody);

        return is_object($mDecoded) ? $mDecoded : (object) [];
    }

    public function header(string $sName): ?string
    {
        $sName = strtolower($sName);

        return isset($this->headers[$sName]) ? (string) $this->headers[$sName] : null;
    }

    public function log(string $sLine): void
    {
        if ($this->cLog !== null) {
            ($this->cLog)($sLine);
        }
    }
}
