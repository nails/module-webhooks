<?php

namespace Nails\Webhooks\Service;

use Nails\Config;
use Nails\Factory;
use Nails\Webhooks\Store\DeliveryLog;

class Log implements DeliveryLog
{
    private ?\Nails\Common\Factory\Logger $oLogger = null;

    private ?string $sFile = null;

    public function line(string $sUuid, string $sLine): void
    {
        $this->logger()->line($sUuid . ' ' . $sLine);
    }

    public function path(): string
    {
        return $this->file();
    }

    public function excerpt(string $sFile, string $sUuid): string
    {
        $sFile = basename($sFile);
        if ($sFile === '' || $sUuid === '') {
            return '';
        }

        $sPath = rtrim((string) Config::get('LOG_DIR'), '/') . '/webhooks/' . $sFile;
        if (!is_file($sPath)) {
            return '';
        }

        $aLines = file($sPath, FILE_IGNORE_NEW_LINES) ?: [];
        $aKeep  = array_filter(
            $aLines,
            static fn(string $sLine): bool => str_contains($sLine, $sUuid),
        );

        return implode("\n", $aKeep);
    }

    private function file(): string
    {
        return $this->sFile ??= 'log-' . date('Y-m-d') . '.php';
    }

    private function logger(): \Nails\Common\Factory\Logger
    {
        if ($this->oLogger === null) {
            /** @var \Nails\Common\Factory\Logger $oLogger */
            $oLogger = Factory::factory('Logger');
            $this->oLogger = $oLogger
                ->setDir('webhooks')
                ->setFile($this->file());
        }

        return $this->oLogger;
    }
}
