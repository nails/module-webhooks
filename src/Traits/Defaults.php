<?php

namespace Nails\Webhooks\Traits;

trait Defaults
{
    public function getDescription(): string
    {
        return '';
    }

    public function isEnabled(): bool
    {
        return true;
    }
}
