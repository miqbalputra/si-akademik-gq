<?php

namespace App\Services;

use App\Models\ReportCard;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class ReportCardPrintOptions
{
    public function save(ReportCard $card, array $options, User $user): void
    {
        DB::transaction(function () use ($card, $options, $user) {
            $card = ReportCard::lockForUpdate()->findOrFail($card->id);
            if ($card->status !== 'draft') {
                throw new DomainException('Rapor terkunci atau terbit tidak dapat diubah.');
            }
            $snapshot = $card->snapshots()->latest('id')->first();
            $data = $snapshot?->snapshot_data;
            if (! isset($data['print'])) {
                throw new DomainException('Pengaturan ini hanya untuk rapor dari template F4.');
            }
            $before = $data['print']['options'];
            $currentDate = ($data['print']['date_policy'] ?? '') === 'admin-period-v1'
                ? $card->academicTerm?->report_issue_date?->format('Y-m-d') : $before['issue_date'];
            if (array_key_exists('issue_date', $options) && $options['issue_date'] !== $currentDate && ! $user->hasRole('admin')) {
                throw new DomainException('Tanggal rapor hanya ditentukan admin.');
            }
            if (($data['print']['date_policy'] ?? '') === 'admin-period-v1') {
                unset($options['issue_date']);
            }
            $data['print']['options'] = array_replace($before, $options);
            $card->update(['homeroom_note' => $options['note'] ?? null]);
            $card->snapshots()->create([
                'layout_version' => $snapshot->layout_version, 'snapshot_data' => $data,
                'generated_at' => now(), 'generated_by' => $user->id,
            ]);
            activity('report_cards')->performedOn($card)->causedBy($user)->withProperties(['before' => $before, 'after' => $options])->log('report_card_print_options_updated');
        });
    }
}
