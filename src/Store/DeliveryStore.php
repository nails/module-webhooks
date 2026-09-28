<?php

namespace Nails\Webhooks\Store;

use Nails\Webhooks\DeliveryRecord;
use Nails\Webhooks\Exception\DuplicateSuccessKey;

interface DeliveryStore
{
    public function findCompleted(string $sScope, string $sKey): ?DeliveryRecord;

    /**
     * @param array<string, mixed> $aRow
     *
     * @throws DuplicateSuccessKey
     */
    public function record(array $aRow): int;
}
