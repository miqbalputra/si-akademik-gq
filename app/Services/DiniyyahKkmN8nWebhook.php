<?php

namespace App\Services;

use App\Models\DiniyyahKkmRevision;
use App\Models\DiniyyahKkmScope;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class DiniyyahKkmN8nWebhook
{
    public function notifySubmitted(DiniyyahKkmScope $scope, DiniyyahKkmRevision $revision, User $teacher, bool $resubmission): void
    {
        $scope->loadMissing(['subject', 'academicTerm.academicYear']);

        $payload = [
            'event' => $resubmission || $revision->version > 1 ? 'resubmitted' : 'submitted',
            'teacher' => $teacher->name,
            'subject' => $scope->subject?->name ?? 'Mapel',
            'mustawa' => 'Mustawa '.$scope->mustawa,
            'semester' => $scope->academicTerm?->kkm_label ?? $scope->academicTerm?->name ?? '-',
            'version' => $revision->version,
            'average' => $revision->average,
            'finalKkm' => $revision->final_kkm,
            'reviewUrl' => route('kabag.kkm.show', $scope),
        ];

        DB::afterCommit(fn () => $this->send($payload));
    }

    private function send(array $payload): void
    {
        $url = trim((string) config('services.n8n.kkm_webhook_url', ''));
        if ($url === '') {
            Log::notice('KKM notification webhook was skipped because N8N_KKM_WEBHOOK_URL is not configured.');

            return;
        }

        try {
            $response = Http::asJson()
                ->acceptJson()
                ->connectTimeout(2)
                ->timeout(max(1, (int) config('services.n8n.kkm_webhook_timeout', 4)))
                ->post($url, $payload);

            if (! $response->successful()) {
                Log::warning('KKM notification webhook returned an unsuccessful response.', ['status' => $response->status()]);
            }
        } catch (Throwable $exception) {
            Log::warning('KKM notification webhook could not be reached.', ['exception' => $exception::class]);
        }
    }
}
