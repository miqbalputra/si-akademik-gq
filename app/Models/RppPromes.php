<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['diniyyah_class_subject_id', 'url', 'disk', 'path', 'nama_file', 'updated_by', 'legacy_source_id', 'source_updated_at'])]
class RppPromes extends Model
{
    use SoftDeletes;

    protected function casts(): array { return ['source_updated_at' => 'datetime']; }
    public function classSubject(): BelongsTo { return $this->belongsTo(DiniyyahClassSubject::class, 'diniyyah_class_subject_id'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
}
