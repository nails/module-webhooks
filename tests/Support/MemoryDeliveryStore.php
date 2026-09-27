<?php

namespace Tests\Webhooks\Support;

use Nails\Webhooks\DeliveryRecord;
use Nails\Webhooks\Exception\DuplicateSuccessKey;
use Nails\Webhooks\Result;
use Nails\Webhooks\Store\DeliveryStore;

class MemoryDeliveryStore implements DeliveryStore
{
    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    public int $missLookups = 0;

    public function findCompleted(string $sScope, string $sKey): ?DeliveryRecord
    {
        if ($this->missLookups > 0) {
            $this->missLookups--;

            return null;
        }

        foreach ($this->rows as $aRow) {
            if (
                $aRow['scope'] === $sScope
                && $aRow['idempotency_key'] === $sKey
                && in_array($aRow['status'], [Result::ACCEPTED, Result::IGNORED], true)
            ) {
                return new DeliveryRecord((int) $aRow['id'], (string) $aRow['uuid'], (string) $aRow['status']);
            }
        }

        return null;
    }

    public function record(array $aRow): int
    {
        $sSuccess = $this->successKey($aRow);
        if ($sSuccess !== null) {
            foreach ($this->rows as $aExisting) {
                if ($aExisting['scope'] === $aRow['scope'] && $this->successKey($aExisting) === $sSuccess) {
                    throw new DuplicateSuccessKey('Duplicate success key');
                }
            }
        }

        $iId        = count($this->rows) + 1;
        $aRow['id'] = $iId;
        $this->rows[] = $aRow;

        return $iId;
    }

    /**
     * @param array<string, mixed> $aRow
     */
    private function successKey(array $aRow): ?string
    {
        if (!in_array($aRow['status'] ?? null, [Result::ACCEPTED, Result::IGNORED], true)) {
            return null;
        }

        $sKey = $aRow['idempotency_key'] ?? null;

        return is_string($sKey) && $sKey !== '' ? $sKey : null;
    }
}
