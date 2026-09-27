<?php

namespace Nails\Webhooks;

class Result
{
    public const ACCEPTED  = 'accepted';
    public const IGNORED   = 'ignored';
    public const FAILED    = 'failed';
    public const CHALLENGE = 'challenged';

    /**
     * @param array<string, string> $aHeaders
     */
    private function __construct(
        public readonly string $status,
        public readonly string $summary,
        public readonly int $httpStatus,
        public readonly string $body,
        public readonly array $headers,
    ) {
    }

    public static function accepted(string $sSummary = ''): self
    {
        return new self(self::ACCEPTED, $sSummary, 200, '', []);
    }

    public static function ignored(string $sSummary = ''): self
    {
        return new self(self::IGNORED, $sSummary, 200, '', []);
    }

    public static function failed(string $sSummary = ''): self
    {
        return new self(self::FAILED, $sSummary, 500, '', []);
    }

    /**
     * @param array<string, string> $aHeaders
     */
    public static function challenge(string $sBody, int $iStatus = 200, array $aHeaders = []): self
    {
        return new self(self::CHALLENGE, 'Challenge', $iStatus, $sBody, $aHeaders);
    }

    public function isChallenge(): bool
    {
        return $this->status === self::CHALLENGE;
    }
}
