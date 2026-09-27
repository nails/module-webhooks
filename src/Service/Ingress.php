<?php

namespace Nails\Webhooks\Service;

use Nails\Factory;
use Nails\Webhooks\Constants;
use Nails\Webhooks\Definition;
use Nails\Webhooks\Delivery;
use Nails\Webhooks\Exception\DuplicateSuccessKey;
use Nails\Webhooks\Exception\RejectedException;
use Nails\Webhooks\HttpResponse;
use Nails\Webhooks\IncomingRequest;
use Nails\Webhooks\Interfaces\Catalogue;
use Nails\Webhooks\Interfaces\Idempotent;
use Nails\Webhooks\Interfaces\Webhook\ProtectedWebhook;
use Nails\Webhooks\Resource\Instance;
use Nails\Webhooks\Result;
use Nails\Webhooks\Store\DeliveryLog;
use Nails\Webhooks\Store\DeliveryStore;
use Nails\Webhooks\Store\InstanceStore;
use Nails\Webhooks\Store\Notifier;
use Throwable;

class Ingress
{
    public function __construct(
        private ?Catalogue $oCatalogue = null,
        private ?DeliveryStore $oDeliveries = null,
        private ?InstanceStore $oInstances = null,
        private ?DeliveryLog $oLog = null,
        private ?Notifier $oNotifier = null,
    ) {
    }

    public function receive(string $sPath, ?IncomingRequest $oRequest = null): HttpResponse
    {
        $oRequest ??= IncomingRequest::capture();
        $sPath = trim($sPath, '/');

        if (!in_array($oRequest->method, ['GET', 'POST'], true)) {
            return new HttpResponse(405);
        }

        [$sSlug, $sToken] = $this->splitPath($sPath);
        if (!$this->catalogue()->has($sSlug)) {
            return new HttpResponse(404);
        }

        $oDefinition = $this->catalogue()->get($sSlug);
        if (!$oDefinition->handler()->isEnabled()) {
            return new HttpResponse(404);
        }

        $oInstance = null;
        if ($oDefinition->isConfigurable()) {
            if ($sToken === null) {
                return new HttpResponse(404);
            }
            $oInstance = $this->instances()->findByToken($sToken);
            if (
                $oInstance === null
                || (string) $oInstance->definition_slug !== $sSlug
                || !$oInstance->isEnabled()
            ) {
                return new HttpResponse(404);
            }
        } elseif ($sToken !== null) {
            return new HttpResponse(404);
        }

        $iStarted = hrtime(true);
        $sUuid    = $this->uuid();
        $oLog     = $this->log();
        $oLog->line($sUuid, sprintf(
            '%s %s from %s slug=%s',
            $oRequest->method,
            $sPath,
            $oRequest->ip,
            $sSlug,
        ));

        $oDelivery = new Delivery(
            $sUuid,
            $oDefinition->handler(),
            $oInstance,
            $oRequest->body,
            $oRequest->method,
            $oRequest->headers,
            $oRequest->query,
            $sSlug,
            static function (string $sLine) use ($oLog, $sUuid): void {
                $oLog->line($sUuid, $sLine);
            },
        );

        $aSensitive = ['authorization', 'cookie'];
        $sKey       = $this->idempotencyKey($oDefinition, $oDelivery);

        try {
            if ($oDefinition->handler() instanceof ProtectedWebhook) {
                $oProtection = $oDefinition->handler()->getProtection($oDelivery);
                $aSensitive  = array_merge($aSensitive, $oProtection->sensitiveHeaders());
                $this->logHeaders($oLog, $sUuid, $oRequest->headers, $aSensitive);
                $oLog->line($sUuid, 'body ' . $oRequest->body);

                $oShortCircuit = $oProtection->verify($oDelivery);
                if ($oShortCircuit !== null) {
                    $oLog->line($sUuid, 'challenge');

                    return $this->finish(
                        $oDefinition,
                        $oInstance,
                        $oDelivery,
                        $oShortCircuit->status,
                        $oShortCircuit->httpStatus,
                        $oShortCircuit->summary,
                        null,
                        $iStarted,
                        $oShortCircuit->body,
                        $oShortCircuit->headers,
                    );
                }
            } else {
                $this->logHeaders($oLog, $sUuid, $oRequest->headers, $aSensitive);
                $oLog->line($sUuid, 'body ' . $oRequest->body);
            }

            if ($sKey !== null) {
                $oExisting = $this->deliveries()->findCompleted($this->scope($oDefinition, $oInstance), $sKey);
                if ($oExisting !== null) {
                    $oLog->line($sUuid, 'duplicate of ' . $oExisting->uuid);

                    return $this->finish(
                        $oDefinition,
                        $oInstance,
                        $oDelivery,
                        Result::IGNORED,
                        200,
                        'Duplicate delivery ' . $oExisting->uuid,
                        null,
                        $iStarted,
                    );
                }
            }

            $oResult = $oDefinition->handler()->handle($oDelivery);
            $oLog->line($sUuid, $oResult->status . ' ' . $oResult->summary);

            return $this->finish(
                $oDefinition,
                $oInstance,
                $oDelivery,
                $oResult->status,
                $oResult->httpStatus,
                $oResult->summary,
                $sKey,
                $iStarted,
                $oResult->body,
                $oResult->headers,
            );
        } catch (RejectedException $oRejected) {
            $oLog->line($sUuid, 'rejected ' . $oRejected->logMessage());

            return $this->finish(
                $oDefinition,
                $oInstance,
                $oDelivery,
                'rejected',
                401,
                $oRejected->getMessage(),
                $sKey,
                $iStarted,
                $oRejected->getMessage(),
            );
        } catch (Throwable $oError) {
            $oLog->line($sUuid, 'failed ' . $oError->getMessage());
            $oLog->line($sUuid, $oError->getTraceAsString());

            return $this->finish(
                $oDefinition,
                $oInstance,
                $oDelivery,
                Result::FAILED,
                500,
                $oError->getMessage(),
                $sKey,
                $iStarted,
            );
        }
    }

    /**
     * @param array<string, string> $aHeaders
     */
    private function finish(
        Definition $oDefinition,
        ?Instance $oInstance,
        Delivery $oDelivery,
        string $sStatus,
        int $iHttpStatus,
        string $sSummary,
        ?string $sKey,
        int $iStarted,
        string $sBody = '',
        array $aHeaders = [],
    ): HttpResponse {
        $iDuration = (int) ((hrtime(true) - $iStarted) / 1_000_000);
        $sSummary  = mb_substr($sSummary, 0, 500);
        $aRow      = [
            'uuid'             => $oDelivery->uuid,
            'definition_class' => $oDefinition->class,
            'definition_slug'  => $oDefinition->slug,
            'instance_id'      => $oInstance?->id,
            'scope'            => $this->scope($oDefinition, $oInstance),
            'status'           => $sStatus,
            'http_status'      => $iHttpStatus,
            'idempotency_key'  => $sKey,
            'summary'          => $sSummary,
            'duration_ms'      => $iDuration,
            'log_file'         => $this->log()->path(),
            'created'          => date('Y-m-d H:i:s'),
        ];

        try {
            $iId = $this->deliveries()->record($aRow);
        } catch (DuplicateSuccessKey) {
            $sExisting = '';
            if ($sKey !== null && $sKey !== '') {
                $oExisting = $this->deliveries()->findCompleted($aRow['scope'], $sKey);
                if ($oExisting !== null) {
                    $sExisting = ' ' . $oExisting->uuid;
                }
            }
            $this->log()->line($oDelivery->uuid, 'duplicate lost the insert race');
            $aRow['status']          = Result::IGNORED;
            $aRow['http_status']     = 200;
            $aRow['idempotency_key'] = null;
            $aRow['summary']         = 'Duplicate delivery' . $sExisting;
            $iId                     = $this->deliveries()->record($aRow);
            $iHttpStatus             = 200;
            $sBody                   = '';
        }

        $this->notifier()->completed($iId);
        $this->log()->line($oDelivery->uuid, 'recorded ' . $iId . ' ' . $aRow['status']);

        return new HttpResponse($iHttpStatus, $sBody, $aHeaders);
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function splitPath(string $sPath): array
    {
        if (preg_match('#^(.*)/([a-f0-9]{64})$#', $sPath, $aMatches) === 1) {
            if ($this->catalogue()->has($aMatches[1]) && $this->catalogue()->get($aMatches[1])->isConfigurable()) {
                return [$aMatches[1], $aMatches[2]];
            }
        }

        return [$sPath, null];
    }

    private function idempotencyKey(Definition $oDefinition, Delivery $oDelivery): ?string
    {
        if ($oDefinition->allowsDuplicates()) {
            return null;
        }

        $oHandler = $oDefinition->handler();
        if ($oHandler instanceof Idempotent) {
            $sKey = $oHandler->getIdempotencyKey($oDelivery);
            if ($sKey !== null && $sKey !== '') {
                return $sKey;
            }
        }

        return hash('sha256', $oDelivery->rawBody);
    }

    private function scope(Definition $oDefinition, ?Instance $oInstance): string
    {
        if ($oInstance !== null && $oInstance->id) {
            return (string) $oInstance->id;
        }

        return 'definition:' . $oDefinition->slug;
    }

    /**
     * @param array<string, string> $aHeaders
     * @param string[]              $aSensitive
     */
    private function logHeaders(DeliveryLog $oLog, string $sUuid, array $aHeaders, array $aSensitive): void
    {
        $aSensitive = array_map('strtolower', $aSensitive);
        foreach ($aHeaders as $sName => $sValue) {
            if (in_array(strtolower((string) $sName), $aSensitive, true)) {
                $sValue = '[redacted, ' . strlen((string) $sValue) . ' bytes]';
            }
            $oLog->line($sUuid, 'header ' . $sName . ': ' . $sValue);
        }
    }

    private function uuid(): string
    {
        $sBytes = random_bytes(16);
        $sBytes[6] = chr(ord($sBytes[6]) & 0x0f | 0x40);
        $sBytes[8] = chr(ord($sBytes[8]) & 0x3f | 0x80);
        $sHex = bin2hex($sBytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($sHex, 0, 8),
            substr($sHex, 8, 4),
            substr($sHex, 12, 4),
            substr($sHex, 16, 4),
            substr($sHex, 20, 12),
        );
    }

    private function catalogue(): Catalogue
    {
        if ($this->oCatalogue === null) {
            /** @var Catalogue $oCatalogue */
            $oCatalogue = Factory::service('Webhook', Constants::MODULE_SLUG);
            $this->oCatalogue = $oCatalogue;
        }

        return $this->oCatalogue;
    }

    private function deliveries(): DeliveryStore
    {
        if ($this->oDeliveries === null) {
            $oModel = Factory::model('Delivery', Constants::MODULE_SLUG);
            if (!$oModel instanceof DeliveryStore) {
                throw new \RuntimeException('Delivery model must implement DeliveryStore');
            }
            $this->oDeliveries = $oModel;
        }

        return $this->oDeliveries;
    }

    private function instances(): InstanceStore
    {
        if ($this->oInstances === null) {
            $oModel = Factory::model('Instance', Constants::MODULE_SLUG);
            if (!$oModel instanceof InstanceStore) {
                throw new \RuntimeException('Instance model must implement InstanceStore');
            }
            $this->oInstances = $oModel;
        }

        return $this->oInstances;
    }

    private function log(): DeliveryLog
    {
        if ($this->oLog === null) {
            /** @var DeliveryLog $oLog */
            $oLog = Factory::service('Log', Constants::MODULE_SLUG);
            $this->oLog = $oLog;
        }

        return $this->oLog;
    }

    private function notifier(): Notifier
    {
        return $this->oNotifier ??= new EventNotifier();
    }
}
