<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['diniyyah_class_subject_id', 'url', 'disk', 'path', 'nama_file', 'updated_by'])]
class RppPromes extends Model
{
    public function classSubject(): BelongsTo { return $this->belongsTo(DiniyyahClassSubject::class, 'diniyyah_class_subject_id'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
}
