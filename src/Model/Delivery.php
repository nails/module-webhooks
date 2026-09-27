<?php

namespace Nails\Webhooks\Model;

use Nails\Common\Model\Base;
use Nails\Factory;
use Nails\Webhooks\Constants;
use Nails\Webhooks\DeliveryRecord;
use Nails\Webhooks\Exception\DuplicateSuccessKey;
use Nails\Webhooks\Result;
use Nails\Webhooks\Store\DeliveryStore;
use Throwable;

class Delivery extends Base implements DeliveryStore
{
    const TABLE               = NAILS_DB_PREFIX . 'webhook_delivery';
    const RESOURCE_NAME       = 'Delivery';
    const RESOURCE_PROVIDER   = Constants::MODULE_SLUG;
    const AUTO_SET_TIMESTAMP  = false;
    const AUTO_SET_USER       = false;
    const DEFAULT_SORT_COLUMN = 'created';

    /**
     * @return string[]
     */
    public function getSearchableColumns(): array
    {
        return ['definition_slug', 'summary', 'status', 'uuid'];
    }

    public function findCompleted(string $sScope, string $sKey): ?DeliveryRecord
    {
        if ($sKey === '') {
            return null;
        }

        /** @var \Nails\Common\Service\Database $oDb */
        $oDb = Factory::service('Database');
        $oDb->where('scope', $sScope);
        $oDb->where('idempotency_key', $sKey);
        $oDb->where_in('status', [Result::ACCEPTED, Result::IGNORED]);
        $oDb->limit(1);
        $oRow = $oDb->get($this->getTableName())->row();

        if (!$oRow) {
            return null;
        }

        return new DeliveryRecord((int) $oRow->id, (string) $oRow->uuid, (string) $oRow->status);
    }

    public function record(array $aRow): int
    {
        try {
            $mId = $this->create($aRow);
        } catch (Throwable $oError) {
            if ($this->isDuplicate($oError->getMessage())) {
                throw new DuplicateSuccessKey($oError->getMessage(), 0, $oError);
            }
            throw $oError;
        }

        if ($mId) {
            return (int) $mId;
        }

        /** @var \Nails\Common\Service\Database $oDb */
        $oDb    = Factory::service('Database');
        $aError = method_exists($oDb, 'error') ? (array) $oDb->error() : [];
        $sError = (string) (($aError['message'] ?? '') . ' ' . ($aError['code'] ?? '') . ' ' . $this->lastError());
        if ($this->isDuplicate($sError)) {
            throw new DuplicateSuccessKey($sError);
        }

        throw new \RuntimeException('Failed to record webhook delivery');
    }

    private function isDuplicate(string $sMessage): bool
    {
        return str_contains($sMessage, '1062') || str_contains(strtolower($sMessage), 'duplicate');
    }
}
