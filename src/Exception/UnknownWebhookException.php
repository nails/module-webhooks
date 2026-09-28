<?php

namespace Nails\Webhooks\Exception;

class UnknownWebhookException extends \RuntimeException
{
    public function __construct(string $sSlug)
    {
        parent::__construct(sprintf('Unknown webhook "%s"', $sSlug));
    }
}
