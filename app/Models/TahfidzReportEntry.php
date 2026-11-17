<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;

/** Append-only revisions. Legacy Tahfidz tables are never written by this flow. */
class TahfidzReportEntry extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['uas_score' => 'decimal:2', 'sabaq_score' => 'decimal:2', 'sabqi_score' => 'decimal:2', 'manzil_score' => 'decimal:2', 'reviewed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new DomainException('Riwayat nilai final tidak boleh ditimpa. Simpan versi baru.'));
        static::deleting(fn () => throw new DomainException('Riwayat nilai final tidak boleh dihapus.'));
    }
}
