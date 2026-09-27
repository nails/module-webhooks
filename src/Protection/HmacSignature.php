<?php

namespace Nails\Webhooks\Protection;

use Nails\Webhooks\Delivery;
use Nails\Webhooks\Exception\RejectedException;
use Nails\Webhooks\Interfaces\Protection;
use Nails\Webhooks\Result;

class HmacSignature implements Protection
{
    public function __construct(
        private readonly string $sSecret,
        private readonly string $sHeader,
        private readonly string $sAlgorithm = 'sha256',
        private readonly string $sEncoding = 'hex',
        private readonly bool $bTimestamp = false,
        private readonly int $iTolerance = 300,
        private readonly ?int $iNow = null,
    ) {
    }

    public function verify(Delivery $oDelivery): ?Result
    {
        $sHeader = (string) $oDelivery->header($this->sHeader);
        if ($sHeader === '') {
            throw new RejectedException('Missing signature header ' . $this->sHeader);
        }

        if ($this->bTimestamp) {
            return $this->verifyTimestamp($oDelivery, $sHeader);
        }

        $sExpected = $this->sign($oDelivery->rawBody);
        if (!hash_equals($sExpected, $sHeader)) {
            throw new RejectedException('Signature mismatch');
        }

        return null;
    }

    public function sensitiveHeaders(): array
    {
        return [$this->sHeader];
    }

    private function verifyTimestamp(Delivery $oDelivery, string $sHeader): ?Result
    {
        /** @var array<string, string[]> $aParts */
        $aParts = [];
        foreach (explode(',', $sHeader) as $sPart) {
            $aPair = explode('=', trim($sPart), 2);
            if (count($aPair) === 2 && $aPair[0] !== '') {
                $aParts[$aPair[0]][] = $aPair[1];
            }
        }

        $sTimestamp = $aParts['t'][0] ?? '';
        if ($sTimestamp === '' || !ctype_digit($sTimestamp)) {
            throw new RejectedException('Signature timestamp missing');
        }

        $iNow = $this->iNow ?? time();
        if (abs($iNow - (int) $sTimestamp) > $this->iTolerance) {
            throw new RejectedException('Signature timestamp outside tolerance');
        }

        $aSignatures = $aParts['v1'] ?? [];
        if ($aSignatures === []) {
            throw new RejectedException('Signature v1 missing');
        }

        $sExpected = $this->sign($sTimestamp . '.' . $oDelivery->rawBody);
        foreach ($aSignatures as $sGiven) {
            if (hash_equals($sExpected, $sGiven)) {
                return null;
            }
        }

        throw new RejectedException('Signature mismatch');
    }

    private function sign(string $sPayload): string
    {
        $sRaw = hash_hmac($this->sAlgorithm, $sPayload, $this->sSecret, true);

        return $this->sEncoding === 'base64' ? base64_encode($sRaw) : bin2hex($sRaw);
    }
}
