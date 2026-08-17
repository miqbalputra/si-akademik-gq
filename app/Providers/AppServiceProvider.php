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
use App\Services\GuruJournalReminderPreferenceService;
use App\Services\GuruPerformaService;
use Filament\Forms\Components\Field;
use Filament\Tables\Columns\Column;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as BladeView;

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

        View::composer('components.layouts.portal', function (BladeView $view): void {
            $user = auth()->user();
            $isGuruPortal = ($view->getData()['portalLabel'] ?? null) === 'Portal Guru';
            $isJournalForm = request()->routeIs(
                'guru.diniyyah-journals.index',
                'guru.diniyyah-tafsir-journals.index',
            );

            if (! $isGuruPortal || $isJournalForm || ! $user?->hasRole('guru') || ! $user->teacher) {
                $view->with('journalOverdueReminder', null);

                return;
            }

            $reminder = app(GuruPerformaService::class)->overdueForActiveTerm($user->teacher);
            $preferences = app(GuruJournalReminderPreferenceService::class);

            if (($reminder['count'] ?? 0) === 0) {
                $preferences->clearSnooze($user);
                $view->with('journalOverdueReminder', null);

                return;
            }

            $snoozedUntil = $preferences->snoozedUntil($user);
            $isSnoozed = $snoozedUntil?->isFuture() ?? false;

            if (! $isSnoozed && $snoozedUntil) {
                $preferences->clearSnooze($user);
            }

            $reminder['is_snoozed'] = $isSnoozed;
            $reminder['snoozed_until'] = $isSnoozed ? $snoozedUntil->toIso8601String() : null;
            $reminder['snoozed_until_label'] = $isSnoozed
                ? $snoozedUntil->format('H:i')
                : null;

            $view->with('journalOverdueReminder', $reminder);
        });

        Field::configureUsing(function (Field $field): void {
            $field->translateLabel();
        });
        Column::configureUsing(function (Column $column): void {
            $column->translateLabel();
        });
    }
}
