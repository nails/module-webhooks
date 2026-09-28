<?php

namespace Tests\Webhooks;

use Nails\Webhooks\Exception\DuplicateSlugException;
use Nails\Webhooks\Exception\UnknownWebhookException;
use Nails\Webhooks\Service\Webhook;
use PHPUnit\Framework\TestCase;
use Tests\Webhooks\Fixture\Alpha\Webhooks\PaymentNotification as AlphaPayment;
use Tests\Webhooks\Fixture\Beta\Webhooks\PaymentNotification as BetaPayment;

class WebhookTest extends TestCase
{
    public function testDerivesASlugFromThePackageAndClass(): void
    {
        $this->assertSame(
            'nails/module-invoice/payment-notification',
            Webhook::slugFor(
                'nails/module-invoice',
                'Nails\\Invoice',
                'Nails\\Invoice\\Webhooks\\PaymentNotification',
            ),
        );
    }

    public function testKeepsNestedSegmentsUnderWebhooks(): void
    {
        $this->assertSame(
            'nails/driver-invoice-stripe/stripe/payment-notification',
            Webhook::slugFor(
                'nails/driver-invoice-stripe',
                'Nails\\Invoice\\Driver\\Payment\\Stripe',
                'Nails\\Invoice\\Driver\\Payment\\Stripe\\Webhooks\\Stripe\\PaymentNotification',
            ),
        );
    }

    public function testDerivesAnAppSlug(): void
    {
        $this->assertSame(
            'app/post-to-channel',
            Webhook::slugFor('app', 'App', 'App\\Webhooks\\PostToChannel'),
        );
    }

    public function testADuplicateDerivedSlugThrows(): void
    {
        $oCatalogue = new Webhook(false);
        $oCatalogue->add(
            'nails/module-invoice',
            'Tests\\Webhooks\\Fixture\\Alpha',
            AlphaPayment::class,
            'Invoice',
        );

        $this->expectException(DuplicateSlugException::class);
        $oCatalogue->add(
            'nails/module-invoice',
            'Tests\\Webhooks\\Fixture\\Beta',
            BetaPayment::class,
            'Invoice',
        );
    }

    public function testGetThrowsForAnUnknownSlug(): void
    {
        $oCatalogue = new Webhook(false);

        $this->expectException(UnknownWebhookException::class);
        $oCatalogue->get('app/missing');
    }
}
