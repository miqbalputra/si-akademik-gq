<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['session_name', 'starts_at', 'ends_at', 'is_break'])]
class ClassSession extends Model
{
    use HasFactory;
    
    protected function casts(): array
    {
        return [
            'is_break' => 'boolean',
        ];
    }

    public function schedules(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DiniyyahTeachingSchedule::class);
    }
}
