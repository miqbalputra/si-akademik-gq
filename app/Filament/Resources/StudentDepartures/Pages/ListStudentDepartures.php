<?php

namespace App\Filament\Resources\StudentDepartures\Pages;

use App\Filament\Resources\StudentDepartures\StudentDepartureResource;
use Filament\Resources\Pages\ListRecords;

class ListStudentDepartures extends ListRecords
{
    protected static string $resource = StudentDepartureResource::class;
}
