<?php

namespace Tests\Webhooks\FindReplace\Exclusion;

use Nails\Webhooks\FindReplace\Exclusion\Package;
use PHPUnit\Framework\TestCase;

class PackageTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!defined('NAILS_DB_PREFIX')) {
            define('NAILS_DB_PREFIX', 'nails_');
        }
    }

    public function testHidden(): void
    {
        self::assertSame(
            [
                NAILS_DB_PREFIX . 'webhook_delivery',
                NAILS_DB_PREFIX . 'webhook_instance.secret',
                NAILS_DB_PREFIX . 'webhook_instance.config',
            ],
            (new Package())->hidden()
        );
    }

    public function testReadOnly(): void
    {
        self::assertSame(
            [
                NAILS_DB_PREFIX . 'webhook_instance.definition_class',
                NAILS_DB_PREFIX . 'webhook_instance.definition_slug',
                NAILS_DB_PREFIX . 'webhook_instance.subscription_status',
            ],
            (new Package())->readOnly()
        );
    }
}
