<?php

namespace App\Services;

use App\Models\TahfidzHalaqah;
use App\Models\TahfidzSemesterRecap;
use App\Models\TahfidzValidation;
use App\Models\User;
use DomainException;

class TahfidzWorkflow
{
    public function submitHalaqah(TahfidzHalaqah $halaqah, User $user): void
    {
        $halaqah->weeklyScores()->where('status', 'draft')->update([
            'status' => 'submitted',
        ]);
    }

    public function approveHalaqah(TahfidzHalaqah $halaqah, User $validator, ?string $notes = null): TahfidzValidation
    {
        $halaqah->weeklyScores()->whereIn('status', ['draft', 'submitted'])->update([
            'status' => 'validated',
        ]);

        return TahfidzValidation::create([
            'tahfidz_halaqah_id' => $halaqah->id,
            'validated_by' => $validator->id,
            'status' => 'approved',
            'validated_at' => now(),
            'notes' => $notes,
        ]);
    }

    public function requestRevisionHalaqah(TahfidzHalaqah $halaqah, User $validator, ?string $notes = null): TahfidzValidation
    {
        $halaqah->weeklyScores()->update(['status' => 'needs_revision']);

        return TahfidzValidation::create([
            'tahfidz_halaqah_id' => $halaqah->id,
            'validated_by' => $validator->id,
            'status' => 'needs_revision',
            'validated_at' => now(),
            'notes' => $notes,
        ]);
    }

    public function lockSemesterRecap(TahfidzSemesterRecap $recap, User $user): void
    {
        if ($recap->locked_at !== null) {
            throw new DomainException('Rekap semester sudah dikunci.');
        }

        $recap->update([
            'status' => 'locked',
            'locked_at' => now(),
        ]);
    }
}