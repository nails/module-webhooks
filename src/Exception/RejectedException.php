<?php

namespace Nails\Webhooks\Exception;

class RejectedException extends \RuntimeException
{
    public function __construct(
        private readonly string $sLogMessage,
        string $sPublicMessage = 'Invalid signature',
    ) {
        parent::__construct($sPublicMessage);
    }

    public function logMessage(): string
    {
        return $this->sLogMessage;
    }
}
