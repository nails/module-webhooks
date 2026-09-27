<?php

namespace Nails\Webhooks;

class HttpResponse
{
    /**
     * @param array<string, string> $aHeaders
     */
    public function __construct(
        public readonly int $httpStatus,
        public readonly string $body = '',
        public readonly array $headers = [],
    ) {
    }
}
