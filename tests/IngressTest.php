<?php

namespace Tests\Webhooks;

use Nails\Webhooks\HttpResponse;
use Nails\Webhooks\IncomingRequest;
use Nails\Webhooks\Resource\Instance;
use Nails\Webhooks\Result;
use Nails\Webhooks\Service\Ingress;
use Nails\Webhooks\Service\Webhook;
use PHPUnit\Framework\TestCase;
use Tests\Webhooks\Fixture\Ingress\Webhooks\Challenge;
use Tests\Webhooks\Fixture\Ingress\Webhooks\Disabled;
use Tests\Webhooks\Fixture\Ingress\Webhooks\EventKeyed;
use Tests\Webhooks\Fixture\Ingress\Webhooks\FailsOnce;
use Tests\Webhooks\Fixture\Ingress\Webhooks\PostToChannel;
use Tests\Webhooks\Fixture\Ingress\Webhooks\Repeatable;
use Tests\Webhooks\Fixture\Ingress\Webhooks\Signed;
use Tests\Webhooks\Fixture\Ingress\Webhooks\Simple;
use Tests\Webhooks\Fixture\Ingress\Webhooks\Throws;
use Tests\Webhooks\Support\MemoryDeliveryStore;
use Tests\Webhooks\Support\MemoryInstanceStore;
use Tests\Webhooks\Support\MemoryLog;
use Tests\Webhooks\Support\MemoryNotifier;

class IngressTest extends TestCase
{
    private Webhook $oCatalogue;

    private MemoryDeliveryStore $oDeliveries;

    private MemoryInstanceStore $oInstances;

    private MemoryLog $oLog;

    private MemoryNotifier $oNotifier;

    private Ingress $oIngress;

    protected function setUp(): void
    {
        Simple::$calls = 0;
        FailsOnce::$calls = 0;
        Throws::$calls = 0;
        Disabled::$calls = 0;
        Repeatable::$calls = 0;
        EventKeyed::$calls = 0;
        Signed::$calls = 0;
        Challenge::$calls = 0;
        PostToChannel::$calls = 0;

        $this->oCatalogue = new Webhook(false);
        $this->register(Simple::class);
        $this->register(FailsOnce::class);
        $this->register(Throws::class);
        $this->register(Disabled::class);
        $this->register(Repeatable::class);
        $this->register(EventKeyed::class);
        $this->register(Signed::class);
        $this->register(Challenge::class);
        $this->register(PostToChannel::class);

        $this->oDeliveries = new MemoryDeliveryStore();
        $this->oInstances  = new MemoryInstanceStore();
        $this->oLog        = new MemoryLog();
        $this->oNotifier   = new MemoryNotifier();
        $this->oIngress    = new Ingress(
            $this->oCatalogue,
            $this->oDeliveries,
            $this->oInstances,
            $this->oLog,
            $this->oNotifier,
        );
    }

    public function testUnknownSlugIs404(): void
    {
        $oResponse = $this->oIngress->receive('missing/handler', $this->request('POST', '{}'));

        $this->assertSame(404, $oResponse->httpStatus);
        $this->assertSame([], $this->oDeliveries->rows);
        $this->assertSame(0, Simple::$calls);
    }

    public function testRejectsMethodsOtherThanGetAndPost(): void
    {
        $oResponse = $this->oIngress->receive($this->slug(Simple::class), $this->request('PUT', '{}'));

        $this->assertSame(405, $oResponse->httpStatus);
        $this->assertSame([], $this->oDeliveries->rows);
    }

    public function testDisabledHandlerIs404(): void
    {
        $oResponse = $this->oIngress->receive($this->slug(Disabled::class), $this->request('POST', '{}'));

        $this->assertSame(404, $oResponse->httpStatus);
        $this->assertSame(0, Disabled::$calls);
        $this->assertSame([], $this->oDeliveries->rows);
    }

    public function testBadSignatureIs401AndDoesNotHandle(): void
    {
        $oResponse = $this->receive(Signed::class, '{}', [
            'x-signature' => 'nope',
        ]);

        $this->assertSame(401, $oResponse->httpStatus);
        $this->assertSame('Invalid signature', $oResponse->body);
        $this->assertSame(0, Signed::$calls);
        $this->assertSame('rejected', $this->oDeliveries->rows[0]['status']);
        $this->assertStringContainsString('Signature mismatch', implode("\n", $this->oLog->lines));
        $this->assertStringNotContainsString('nope', implode("\n", $this->oLog->lines));
    }

    public function testAcceptedDeliveryIs200AndAudited(): void
    {
        $sBody     = '{"ok":true}';
        $oResponse = $this->receive(Simple::class, $sBody);

        $this->assertSame(200, $oResponse->httpStatus);
        $this->assertSame(1, Simple::$calls);
        $this->assertCount(1, $this->oDeliveries->rows);
        $this->assertSame(Result::ACCEPTED, $this->oDeliveries->rows[0]['status']);
        $this->assertSame(hash('sha256', $sBody), $this->oDeliveries->rows[0]['idempotency_key']);
        $this->assertSame('definition:' . $this->slug(Simple::class), $this->oDeliveries->rows[0]['scope']);
        $this->assertSame([1], $this->oNotifier->ids);
    }

    public function testAThrownExceptionIs500(): void
    {
        $oResponse = $this->receive(Throws::class, '{}');

        $this->assertSame(500, $oResponse->httpStatus);
        $this->assertSame(1, Throws::$calls);
        $this->assertSame(Result::FAILED, $this->oDeliveries->rows[0]['status']);
        $this->assertSame('boom', $this->oDeliveries->rows[0]['summary']);
    }

    public function testASecondIdenticalBodyAfterAcceptedIsIgnored(): void
    {
        $sBody = '{"id":9}';
        $this->receive(Simple::class, $sBody);
        $sOriginal = (string) $this->oDeliveries->rows[0]['uuid'];

        $oResponse = $this->receive(Simple::class, $sBody);

        $this->assertSame(200, $oResponse->httpStatus);
        $this->assertSame(1, Simple::$calls);
        $this->assertCount(2, $this->oDeliveries->rows);
        $this->assertSame(Result::IGNORED, $this->oDeliveries->rows[1]['status']);
        $this->assertNull($this->oDeliveries->rows[1]['idempotency_key']);
        $this->assertSame('Duplicate delivery ' . $sOriginal, $this->oDeliveries->rows[1]['summary']);
    }

    public function testASecondIdenticalBodyAfterFailedDoesHandle(): void
    {
        $sBody = '{"retry":true}';
        $oFirst = $this->receive(FailsOnce::class, $sBody);
        $oSecond = $this->receive(FailsOnce::class, $sBody);

        $this->assertSame(500, $oFirst->httpStatus);
        $this->assertSame(200, $oSecond->httpStatus);
        $this->assertSame(2, FailsOnce::$calls);
        $this->assertSame(Result::FAILED, $this->oDeliveries->rows[0]['status']);
        $this->assertSame(Result::ACCEPTED, $this->oDeliveries->rows[1]['status']);
    }

    public function testAllowsDuplicatesHandlesEveryIdenticalBody(): void
    {
        $sBody = '{"same":true}';
        $this->receive(Repeatable::class, $sBody);
        $this->receive(Repeatable::class, $sBody);

        $this->assertSame(2, Repeatable::$calls);
        $this->assertSame(Result::ACCEPTED, $this->oDeliveries->rows[0]['status']);
        $this->assertSame(Result::ACCEPTED, $this->oDeliveries->rows[1]['status']);
        $this->assertNull($this->oDeliveries->rows[0]['idempotency_key']);
        $this->assertNull($this->oDeliveries->rows[1]['idempotency_key']);
    }

    public function testAnIdempotencyKeyIgnoresADifferentBody(): void
    {
        $this->receive(EventKeyed::class, '{"id":"evt_1","n":1}');
        $oResponse = $this->receive(EventKeyed::class, '{"id":"evt_1","n":2}');

        $this->assertSame(200, $oResponse->httpStatus);
        $this->assertSame(1, EventKeyed::$calls);
        $this->assertSame(Result::IGNORED, $this->oDeliveries->rows[1]['status']);
        $this->assertSame('evt_1', $this->oDeliveries->rows[0]['idempotency_key']);
    }

    public function testChallengeReturnsItsBodyAndDoesNotHandle(): void
    {
        $oResponse = $this->receive(Challenge::class, '');

        $this->assertSame(200, $oResponse->httpStatus);
        $this->assertSame('pong', $oResponse->body);
        $this->assertSame(['X-Challenge' => '1'], $oResponse->headers);
        $this->assertSame(0, Challenge::$calls);
        $this->assertSame(Result::CHALLENGE, $this->oDeliveries->rows[0]['status']);
        $this->assertNotSame('rejected', $this->oDeliveries->rows[0]['status']);
    }

    public function testAConfigurableInstanceRequiresAMatchingToken(): void
    {
        $sSlug  = $this->slug(PostToChannel::class);
        $sToken = str_repeat('ab', 32);

        $oMissing = $this->oIngress->receive($sSlug, $this->request('POST', '{}'));
        $this->assertSame(404, $oMissing->httpStatus);

        $oUnknown = $this->oIngress->receive($sSlug . '/' . $sToken, $this->request('POST', '{}'));
        $this->assertSame(404, $oUnknown->httpStatus);

        $oDisabled = $this->instance($sSlug, $sToken, '0');
        $this->oInstances->add($oDisabled);
        $oDisabledResponse = $this->oIngress->receive($sSlug . '/' . $sToken, $this->request('POST', '{}'));
        $this->assertSame(404, $oDisabledResponse->httpStatus);
        $this->assertSame(0, PostToChannel::$calls);

        $sEnabled = str_repeat('cd', 32);
        $oEnabled = $this->instance($sSlug, $sEnabled, '1');
        $this->oInstances->add($oEnabled);
        $oOk = $this->oIngress->receive($sSlug . '/' . $sEnabled, $this->request('POST', '{}'));

        $this->assertSame(200, $oOk->httpStatus);
        $this->assertSame(1, PostToChannel::$calls);
        $this->assertSame('9', $this->oDeliveries->rows[0]['scope']);
        $this->assertSame(9, $this->oDeliveries->rows[0]['instance_id']);
    }

    public function testASingletonUrlWithATokenIs404(): void
    {
        $sPath = $this->slug(Simple::class) . '/' . str_repeat('ab', 32);
        $oResponse = $this->oIngress->receive($sPath, $this->request('POST', '{}'));

        $this->assertSame(404, $oResponse->httpStatus);
        $this->assertSame(0, Simple::$calls);
    }

    public function testALostInsertRaceRecordsAnIgnoredDelivery(): void
    {
        $sBody = '{"race":true}';
        $sKey  = hash('sha256', $sBody);
        $this->oDeliveries->record([
            'uuid'             => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'definition_class' => Simple::class,
            'definition_slug'  => $this->slug(Simple::class),
            'instance_id'      => null,
            'scope'            => 'definition:' . $this->slug(Simple::class),
            'status'           => Result::ACCEPTED,
            'http_status'      => 200,
            'idempotency_key'  => $sKey,
            'summary'          => 'winner',
            'duration_ms'      => 1,
            'log_file'         => 'log-test.php',
            'created'          => '2026-01-01 00:00:00',
        ]);
        $this->oDeliveries->missLookups = 1;

        $oResponse = $this->receive(Simple::class, $sBody);

        $this->assertSame(200, $oResponse->httpStatus);
        $this->assertSame('', $oResponse->body);
        $this->assertSame(1, Simple::$calls);
        $this->assertCount(2, $this->oDeliveries->rows);
        $this->assertSame(Result::IGNORED, $this->oDeliveries->rows[1]['status']);
        $this->assertNull($this->oDeliveries->rows[1]['idempotency_key']);
        $this->assertSame(
            'Duplicate delivery aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            $this->oDeliveries->rows[1]['summary'],
        );
    }

    public function testAGoodSignatureIsRedactedInTheLog(): void
    {
        $sBody = '{"signed":true}';
        $sSignature = hash_hmac('sha256', $sBody, Signed::SECRET);
        $oResponse = $this->receive(Signed::class, $sBody, [
            'x-signature' => $sSignature,
            'authorization' => 'Bearer secret-token',
        ]);

        $sLog = implode("\n", $this->oLog->lines);
        $this->assertSame(200, $oResponse->httpStatus);
        $this->assertSame(1, Signed::$calls);
        $this->assertStringContainsString('[redacted, ' . strlen($sSignature) . ' bytes]', $sLog);
        $this->assertStringContainsString('[redacted, ' . strlen('Bearer secret-token') . ' bytes]', $sLog);
        $this->assertStringNotContainsString($sSignature, $sLog);
        $this->assertStringNotContainsString('Bearer secret-token', $sLog);
    }

    private function register(string $sClass): void
    {
        $this->oCatalogue->add('tests/ingress', 'Tests\\Webhooks\\Fixture\\Ingress', $sClass, 'Ingress');
    }

    private function slug(string $sClass): string
    {
        return Webhook::slugFor('tests/ingress', 'Tests\\Webhooks\\Fixture\\Ingress', $sClass);
    }

    /**
     * @param array<string, string> $aHeaders
     */
    private function receive(string $sClass, string $sBody, array $aHeaders = []): HttpResponse
    {
        return $this->oIngress->receive($this->slug($sClass), $this->request('POST', $sBody, $aHeaders));
    }

    /**
     * @param array<string, string> $aHeaders
     */
    private function request(string $sMethod, string $sBody, array $aHeaders = []): IncomingRequest
    {
        return new IncomingRequest($sMethod, $sBody, $aHeaders, [], '127.0.0.1');
    }

    private function instance(string $sSlug, string $sToken, string $sEnabled): Instance
    {
        $oInstance = new Instance();
        $oInstance->id = 9;
        $oInstance->definition_slug = $sSlug;
        $oInstance->token = $sToken;
        $oInstance->is_enabled = $sEnabled;

        return $oInstance;
    }
}
