<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'short_name', 'address', 'phone', 'email', 'logo_path'])]
class School extends Model
{
    use HasFactory;

    public function academicYears(): HasMany
    {
        return $this->hasMany(AcademicYear::class);
    }

    public function schoolHolidays(): HasMany
    {
        return $this->hasMany(SchoolHoliday::class);
    }

    public function schoolEvents(): HasMany
    {
        return $this->hasMany(SchoolEvent::class);
    }
}
