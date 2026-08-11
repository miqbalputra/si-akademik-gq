<?php

namespace App\Providers;

use App\Models\DiniyyahClassJournal;
use App\Models\DiniyyahClassJournalAbsence;
use App\Models\DiniyyahScore;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\DiniyyahTeachingSchedule;
use App\Models\HomeroomAssignment;
use App\Models\SchoolEvent;
use App\Models\StudentAttendance;
use App\Models\TahfidzUasScore;
use App\Models\TahfidzWeeklyScore;
use App\Models\TasmiExaminerAssignment;
use App\Models\TasmiRecord;
use App\Observers\DiniyyahClassJournalAbsenceObserver;
use App\Observers\DiniyyahClassJournalObserver;
use App\Observers\DiniyyahScoreObserver;
use App\Observers\DiniyyahTeacherAssignmentObserver;
use App\Observers\DiniyyahTeachingScheduleObserver;
use App\Observers\HomeroomAssignmentObserver;
use App\Observers\SchoolEventObserver;
use App\Observers\StudentAttendanceObserver;
use App\Observers\TahfidzUasScoreObserver;
use App\Observers\TahfidzWeeklyScoreObserver;
use App\Observers\TasmiExaminerAssignmentObserver;
use App\Observers\TasmiRecordObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        DiniyyahScore::observe(DiniyyahScoreObserver::class);
        DiniyyahClassJournal::observe(DiniyyahClassJournalObserver::class);
        DiniyyahClassJournalAbsence::observe(DiniyyahClassJournalAbsenceObserver::class);
        DiniyyahTeachingSchedule::observe(DiniyyahTeachingScheduleObserver::class);
        DiniyyahTeacherAssignment::observe(DiniyyahTeacherAssignmentObserver::class);
        TasmiRecord::observe(TasmiRecordObserver::class);
        TasmiExaminerAssignment::observe(TasmiExaminerAssignmentObserver::class);
        StudentAttendance::observe(StudentAttendanceObserver::class);
        TahfidzWeeklyScore::observe(TahfidzWeeklyScoreObserver::class);
        TahfidzUasScore::observe(TahfidzUasScoreObserver::class);
        HomeroomAssignment::observe(HomeroomAssignmentObserver::class);
        SchoolEvent::observe(SchoolEventObserver::class);

        \Filament\Forms\Components\Field::configureUsing(function (\Filament\Forms\Components\Field $field): void {
            $field->translateLabel();
        });
        \Filament\Tables\Columns\Column::configureUsing(function (\Filament\Tables\Columns\Column $column): void {
            $column->translateLabel();
        });
    }
}
