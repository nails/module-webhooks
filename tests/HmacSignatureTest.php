<?php

namespace Tests\Webhooks;

use Nails\Webhooks\Delivery;
use Nails\Webhooks\Exception\RejectedException;
use Nails\Webhooks\Protection\HmacSignature;
use PHPUnit\Framework\TestCase;
use Tests\Webhooks\Fixture\Alpha\Webhooks\PaymentNotification;

class HmacSignatureTest extends TestCase
{
    public function testAcceptsAGoodSignature(): void
    {
        $sBody = '{"id":1}';
        $oProtection = new HmacSignature('sekret', 'X-Signature');

        $this->assertNull($oProtection->verify($this->delivery($sBody, [
            'x-signature' => hash_hmac('sha256', $sBody, 'sekret'),
        ])));
    }

    public function testRejectsABadSignature(): void
    {
        $oProtection = new HmacSignature('sekret', 'X-Signature');

        try {
            $oProtection->verify($this->delivery('{"id":1}', [
                'x-signature' => 'not-the-signature',
            ]));
            $this->fail('Expected a rejection');
        } catch (RejectedException $oRejected) {
            $this->assertSame('Signature mismatch', $oRejected->logMessage());
            $this->assertSame('Invalid signature', $oRejected->getMessage());
        }
    }

    public function testRejectsAnOldTimestamp(): void
    {
        $sBody = '{"id":1}';
        $sTimestamp = '1700000000';
        $sSignature = hash_hmac('sha256', $sTimestamp . '.' . $sBody, 'sekret');
        $oProtection = new HmacSignature('sekret', 'Stripe-Signature', 'sha256', 'hex', true, 300, 1700000301);

        $this->expectException(RejectedException::class);
        $oProtection->verify($this->delivery($sBody, [
            'stripe-signature' => 't=' . $sTimestamp . ',v1=' . $sSignature,
        ]));
    }

    public function testAcceptsEitherV1DuringRotation(): void
    {
        $sBody = '{"id":1}';
        $sTimestamp = '1700000000';
        $sSignature = hash_hmac('sha256', $sTimestamp . '.' . $sBody, 'current');
        $oProtection = new HmacSignature('current', 'Stripe-Signature', 'sha256', 'hex', true, 300, 1700000000);

        $this->assertNull($oProtection->verify($this->delivery($sBody, [
            'stripe-signature' => 't=' . $sTimestamp . ',v1=deadbeef,v1=' . $sSignature,
        ])));
    }

    /**
     * @param array<string, string> $aHeaders
     */
    private function delivery(string $sBody, array $aHeaders): Delivery
    {
        return new Delivery(
            '11111111-1111-4111-8111-111111111111',
            new PaymentNotification(),
            null,
            $sBody,
            'POST',
            $aHeaders,
            [],
            'nails/module-invoice/payment-notification',
        );
    }
}
