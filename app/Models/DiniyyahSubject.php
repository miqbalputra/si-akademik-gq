<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'name', 'arabic_name', 'default_assessment_method', 'sort_order', 'is_active'])]
class DiniyyahSubject extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function classSubjects(): HasMany
    {
        return $this->hasMany(DiniyyahClassSubject::class, 'subject_id');
    }
}
