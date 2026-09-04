<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SyncRppSourceEntity;
use App\Models\RppSyncEvent;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RppSyncWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if (! config('rpp_sync.enabled') || ! config('rpp_sync.webhook_secret')) abort(404);
        $raw = $request->getContent();
        $timestamp = (string) $request->header('X-Rpp-Event-Timestamp');
        $signature = (string) $request->header('X-Rpp-Signature');
        $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$raw, (string) config('rpp_sync.webhook_secret'));
        if (! $timestamp || ! hash_equals($expected, $signature)) return response()->json(['message' => 'Signature webhook tidak valid.'], 401);
        try {
            $occurredAt = Carbon::parse($timestamp);
        } catch (\Throwable) {
            return response()->json(['message' => 'Timestamp webhook tidak valid.'], 422);
        }
        if ($occurredAt->diffInMinutes(now(), true) > 10) return response()->json(['message' => 'Webhook sudah kedaluwarsa.'], 422);
        $data = $request->validate(['eventId' => ['required', 'uuid'], 'event' => ['required', 'in:rpp.upsert,rpp.deleted,promes.upsert,promes.deleted'], 'entityId' => ['required', 'string', 'max:191'], 'occurredAt' => ['required', 'date']]);
        if (! Carbon::parse($data['occurredAt'])->equalTo($occurredAt)) return response()->json(['message' => 'Timestamp payload tidak cocok.'], 422);
        $event = RppSyncEvent::firstOrCreate(
            ['event_id' => $data['eventId']],
            ['event_type' => $data['event'], 'entity_id' => $data['entityId'], 'occurred_at' => $occurredAt, 'payload_hash' => hash('sha256', $raw)],
        );
        if ($event->wasRecentlyCreated) {
            SyncRppSourceEntity::dispatch(str_starts_with($data['event'], 'promes.') ? 'promes' : 'rpp', $data['entityId'], $event->id);
        }
        return response()->json(['accepted' => true, 'duplicate' => ! $event->wasRecentlyCreated], 202);
    }
}
