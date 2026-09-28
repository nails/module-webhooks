<?php

namespace Tests\Webhooks;

use Nails\Webhooks\Delivery;
use Nails\Webhooks\Exception\RejectedException;
use Nails\Webhooks\Protection\SharedSecretHeader;
use PHPUnit\Framework\TestCase;
use Tests\Webhooks\Fixture\Alpha\Webhooks\PaymentNotification;

class SharedSecretHeaderTest extends TestCase
{
    public function testMatchesTheHeader(): void
    {
        $oProtection = new SharedSecretHeader('sekret');

        $this->assertNull($oProtection->verify($this->delivery([
            'x-webhook-token' => 'sekret',
        ])));
    }

    public function testRejectsAMismatch(): void
    {
        $oProtection = new SharedSecretHeader('sekret');

        $this->expectException(RejectedException::class);
        $oProtection->verify($this->delivery([
            'x-webhook-token' => 'wrong',
        ]));
    }

    public function testIgnoresAQueryTokenByDefault(): void
    {
        $oProtection = new SharedSecretHeader('sekret');

        $this->expectException(RejectedException::class);
        $oProtection->verify($this->delivery([], ['token' => 'sekret']));
    }

    public function testAcceptsAQueryTokenWhenAllowed(): void
    {
        $oProtection = new SharedSecretHeader('sekret', 'X-Webhook-Token', true);

        $this->assertNull($oProtection->verify($this->delivery([], ['token' => 'sekret'])));
    }

    /**
     * @param array<string, string> $aHeaders
     * @param array<string, mixed>  $aQuery
     */
    private function delivery(array $aHeaders, array $aQuery = []): Delivery
    {
        return new Delivery(
            '11111111-1111-4111-8111-111111111111',
            new PaymentNotification(),
            null,
            '',
            'POST',
            $aHeaders,
            $aQuery,
            'app/post-to-channel',
        );
    }
}
