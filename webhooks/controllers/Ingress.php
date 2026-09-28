<?php

use Nails\Factory;
use Nails\Webhooks\Constants;
use Nails\Webhooks\Service\Ingress as IngressService;

/**
 * Public webhook ingress. The route passes the remainder of /webhooks/ here.
 */
class Ingress extends \Nails\Common\Controller\Base
{
    public function index(string $sPath = '', string ...$aRest): void
    {
        if ($aRest !== []) {
            $sPath = trim($sPath . '/' . implode('/', $aRest), '/');
        }

        /** @var IngressService $oIngress */
        $oIngress  = Factory::service('Ingress', Constants::MODULE_SLUG);
        $oResponse = $oIngress->receive($sPath);

        /** @var \Nails\Common\Service\Output $oOutput */
        $oOutput = Factory::service('Output');
        $oOutput->setStatusHeader($oResponse->httpStatus);
        $oOutput->setHeader('Content-Type: text/plain; charset=utf-8');

        foreach ($oResponse->headers as $sName => $sValue) {
            $oOutput->setHeader($sName . ': ' . $sValue);
        }

        $oOutput->setOutput($oResponse->body);
    }
}
