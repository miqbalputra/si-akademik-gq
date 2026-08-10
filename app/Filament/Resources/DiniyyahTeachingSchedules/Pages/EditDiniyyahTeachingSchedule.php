<?php

namespace App\Filament\Resources\DiniyyahTeachingSchedules\Pages;

use App\Filament\Resources\DiniyyahTeachingSchedules\DiniyyahTeachingScheduleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDiniyyahTeachingSchedule extends EditRecord
{
    protected static string $resource = DiniyyahTeachingScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Menghapus baris jadwal AMAN bagi jurnal kelas: jurnal terhubung
            // ke penugasan (assignment), bukan ke jadwal. Beri reassurance
            // eksplisit agar admin tidak ragu saat menyesuaikan jadwal.
            DeleteAction::make()
                ->successNotificationTitle('Jadwal dihapus. Jurnal kelas tidak terpengaruh.'),
        ];
    }
}