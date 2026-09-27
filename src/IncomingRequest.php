<?php

namespace Nails\Webhooks;

class IncomingRequest
{
    /**
     * @param array<string, string> $aHeaders
     * @param array<string, mixed>  $aQuery
     */
    public function __construct(
        public readonly string $method,
        public readonly string $body,
        public readonly array $headers,
        public readonly array $query,
        public readonly string $ip,
    ) {
    }

    public static function capture(): self
    {
        $aHeaders = [];

        if (function_exists('getallheaders')) {
            foreach (getallheaders() ?: [] as $sName => $sValue) {
                $aHeaders[strtolower((string) $sName)] = (string) $sValue;
            }
        }

        foreach ($_SERVER as $sKey => $mValue) {
            if (!is_string($sKey) || !is_scalar($mValue)) {
                continue;
            }
            if (str_starts_with($sKey, 'HTTP_')) {
                $sName = strtolower(str_replace('_', '-', substr($sKey, 5)));
                $aHeaders[$sName] ??= (string) $mValue;
            }
        }

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            (string) file_get_contents('php://input'),
            $aHeaders,
            $_GET,
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        );
    }
}
