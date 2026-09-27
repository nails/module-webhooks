<?php

namespace Nails\Webhooks\Traits;

use Nails\Webhooks\Constants;
use Nails\Webhooks\Delivery;
use Nails\Webhooks\Interfaces\Protection;
use Nails\Webhooks\Protection\HmacSignature;

trait SignsPayload
{
    abstract protected function secret(Delivery $oDelivery): string;

    abstract protected function signatureHeader(): string;

    protected function signatureAlgorithm(): string
    {
        return 'sha256';
    }

    /**
     * hex or base64
     */
    protected function signatureEncoding(): string
    {
        return 'hex';
    }

    /**
     * When true, the header is t=<unix>,v1=<hex> and the signed payload is "{timestamp}.{body}".
     */
    protected function signsTimestamp(): bool
    {
        return false;
    }

    protected function signatureTolerance(): int
    {
        return Constants::SIGNATURE_TOLERANCE;
    }

    public function getProtection(Delivery $oDelivery): Protection
    {
        return new HmacSignature(
            $this->secret($oDelivery),
            $this->signatureHeader(),
            $this->signatureAlgorithm(),
            $this->signatureEncoding(),
            $this->signsTimestamp(),
            $this->signatureTolerance(),
        );
    }
}
