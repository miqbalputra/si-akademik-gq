<?php

namespace Tests\Feature;

use App\Jobs\SyncRppSourceEntity;
use App\Models\RppSyncEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RppSyncWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('rpp_sync.enabled', true);
        config()->set('rpp_sync.webhook_secret', 'test-webhook-secret');
        Queue::fake();
    }

    public function test_signed_rpp_webhook_is_recorded_and_queued_once(): void
    {
        $payload = ['eventId' => '0f4d17ea-466b-4bd5-b2bc-1fa8b7b7e3c9', 'event' => 'rpp.upsert', 'entityId' => 'rpp-source-1', 'occurredAt' => now()->toIso8601String()];
        $response = $this->postWebhook($payload);

        $response->assertAccepted()->assertJsonPath('accepted', true);
        $this->assertDatabaseHas('rpp_sync_events', ['event_id' => $payload['eventId'], 'status' => 'received']);
        Queue::assertPushed(SyncRppSourceEntity::class, fn (SyncRppSourceEntity $job): bool => $job->entity === 'rpp' && $job->entityId === 'rpp-source-1');

        $this->postWebhook($payload)->assertAccepted()->assertJsonPath('duplicate', true);
        $this->assertSame(1, RppSyncEvent::count());
        Queue::assertPushed(SyncRppSourceEntity::class, 1);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $payload = ['eventId' => '1426320b-c710-4fe5-b91c-7ee4a4052a6a', 'event' => 'promes.upsert', 'entityId' => 'promes-source-1', 'occurredAt' => now()->toIso8601String()];
        $this->withHeaders(['X-Rpp-Event-Timestamp' => $payload['occurredAt'], 'X-Rpp-Signature' => 'sha256=invalid'])->postJson('/api/integrations/rpp/webhook', $payload)->assertUnauthorized();
        Queue::assertNothingPushed();
    }

    private function postWebhook(array $payload)
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = $payload['occurredAt'];
        $signature = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, 'test-webhook-secret');
        return $this->call('POST', '/api/integrations/rpp/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_RPP_EVENT_TIMESTAMP' => $timestamp, 'HTTP_X_RPP_SIGNATURE' => $signature], $body);
    }
}
