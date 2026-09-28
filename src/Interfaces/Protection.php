<?php

namespace Nails\Webhooks\Interfaces;

use Nails\Webhooks\Delivery;
use Nails\Webhooks\Result;

interface Protection
{
    /**
     * Returns null when the request may be handled.
     * Returns a Result to short-circuit, typically a challenge response.
     *
     * @throws \Nails\Webhooks\Exception\RejectedException
     */
    public function verify(Delivery $oDelivery): ?Result;

    /**
     * Header names whose values must not be written to the file log
     *
     * @return string[]
     */
    public function sensitiveHeaders(): array;
}
