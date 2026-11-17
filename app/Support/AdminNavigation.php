<?php

namespace App\Support;

use App\Filament\Pages;
use App\Filament\Resources;
use App\Models\User;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;

/** Presentation and destination registry. Resource/page policies remain authoritative. */
class AdminNavigation
{
    public const GROUPS = ['Khusus Admin', 'Rapor', 'Data Sekolah', 'Diniyyah', 'Tahfidz'];

    public function definitions(): array
    {
        $groups = [
            'Khusus Admin' => [
                Resources\Users\UserResource::class,
                Resources\StudentDepartures\StudentDepartureResource::class,
                Pages\ClassPlacementBoard::class,
                Pages\SessionTimeMatrix::class,
                Resources\RppAiSettings\RppAiSettingResource::class,
                Pages\AppearanceSettings::class,
            ],
            'Rapor' => [Resources\ReportCards\ReportCardResource::class],
            'Data Sekolah' => [
                Resources\Schools\SchoolResource::class,
                Resources\Students\StudentResource::class,
                Resources\Guardians\GuardianResource::class,
                Resources\Teachers\TeacherResource::class,
                Resources\AcademicYears\AcademicYearResource::class,
                Resources\AcademicTerms\AcademicTermResource::class,
                Pages\AcademicCalendar::class,
                Resources\SchoolHolidays\SchoolHolidayResource::class,
                Resources\SchoolEvents\SchoolEventResource::class,
                Resources\NoKbmAgendas\NoKbmAgendaResource::class,
                Resources\Classrooms\ClassroomResource::class,
                Resources\ClassroomTerms\ClassroomTermResource::class,
                Resources\ClassEnrollments\ClassEnrollmentResource::class,
                Resources\HomeroomAssignments\HomeroomAssignmentResource::class,
                Pages\AttendanceShortcut::class,
                Pages\AttendanceIntegrationStatus::class,
            ],
            'Diniyyah' => [
                Resources\ClassSessions\ClassSessionResource::class,
                Pages\SessionTimeComparison::class,
                Resources\DiniyyahSubjects\DiniyyahSubjectResource::class,
                Resources\DiniyyahClassSubjects\DiniyyahClassSubjectResource::class,
                Pages\Kkm::class,
                Resources\DiniyyahTeacherAssignments\DiniyyahTeacherAssignmentResource::class,
                Pages\RingkasanPenugasanGuru::class,
                Resources\DiniyyahTeachingSchedules\DiniyyahTeachingScheduleResource::class,
                Pages\DiniyyahScheduleVersionManager::class,
                Resources\DiniyyahScheduleChangeLogs\DiniyyahScheduleChangeLogResource::class,
                Resources\DiniyyahClassJournals\DiniyyahClassJournalResource::class,
                Pages\RekapJurnalGuru::class,
                Resources\DiniyyahAssessmentSets\DiniyyahAssessmentSetResource::class,
                Resources\DiniyyahScoreComponents\DiniyyahScoreComponentResource::class,
                Resources\DiniyyahScores\DiniyyahScoreResource::class,
                Resources\DiniyyahAssessmentResults\DiniyyahAssessmentResultResource::class,
                Resources\DiniyyahScoreValidations\DiniyyahScoreValidationResource::class,
                Resources\RppPromes\RppPromesResource::class,
                Resources\Rpps\RppResource::class,
                Pages\RppSyncDashboard::class,
                Pages\RppBroadcast::class,
            ],
            'Tahfidz' => [
                Resources\TahfidzHalaqahs\TahfidzHalaqahResource::class,
                Pages\HalaqahPlacementBoard::class,
                Resources\TahfidzWeeks\TahfidzWeekResource::class,
                Resources\TahfidzUasDays\TahfidzUasDayResource::class,
                Resources\TahfidzUasCategories\TahfidzUasCategoryResource::class,
                Resources\TasmiExaminerAssignments\TasmiExaminerAssignmentResource::class,
                Resources\TasmiRecords\TasmiRecordResource::class,
            ],
        ];

        $definitions = ['dashboard' => ['source' => Dashboard::class, 'group' => null]];
        foreach ($groups as $group => $sources) {
            foreach ($sources as $source) {
                $definitions[class_basename($source)] = ['source' => $source, 'group' => $group];
            }
        }

        return $definitions + [
            'class-rankings' => ['group' => 'Rapor', 'label' => 'Peringkat Semua Kelas', 'route' => 'admin.class-rankings.index', 'roles' => ['admin'], 'icon' => 'heroicon-o-chart-bar'],
            'journal-reminders' => ['group' => 'Diniyyah', 'label' => 'Pengingat Jurnal KBM', 'route' => 'admin.journal-reminders.index', 'roles' => ['admin', 'kabag_diniyyah'], 'icon' => 'heroicon-o-bell-alert'],
            'monthly-jp' => ['group' => 'Diniyyah', 'label' => 'Rekap JP Bulanan', 'route' => 'admin.monthly-jp-recap.index', 'roles' => ['admin', 'kabag_diniyyah', 'kepala_sekolah'], 'icon' => 'heroicon-o-chart-bar'],
            'tasmi-report' => ['group' => 'Tahfidz', 'label' => 'Rekap & Ekspor Tasmi', 'route' => 'admin.tasmi-report.index', 'roles' => ['admin', 'kabag_tahfidz'], 'icon' => 'heroicon-o-arrow-down-tray'],
        ];
    }

    public function canAccess(string $key, ?User $user): bool
    {
        $entry = $this->definitions()[$key] ?? null;
        if (! $entry || ! $user || ! $user->canAccessPanel(filament()->getPanel('admin'))) {
            return false;
        }

        if (isset($entry['source'])) {
            $source = $entry['source'];

            return $source::shouldRegisterNavigation() && $source::canAccess();
        }

        // These match the existing standalone report controllers' authorization.
        return $user->hasAnyRole($entry['roles']);
    }

    public function url(string $key): string
    {
        $entry = $this->definitions()[$key];

        return isset($entry['source']) ? $entry['source']::getNavigationUrl() : route($entry['route']);
    }

    public function keyForUrl(string $url): ?string
    {
        $request = request();
        $urls = $request->attributes->get('gq.admin-navigation.urls');
        if ($urls === null) {
            $urls = [];
            foreach ($this->definitions() as $key => $entry) {
                $urls[$this->url($key)] = $key;
            }
            $request->attributes->set('gq.admin-navigation.urls', $urls);
        }

        return $urls[$url] ?? null;
    }

    public function build(NavigationBuilder $builder): NavigationBuilder
    {
        $user = auth('admin')->user();
        $groups = array_fill_keys(self::GROUPS, []);
        foreach ($this->definitions() as $key => $entry) {
            if (! $this->canAccess($key, $user)) {
                continue;
            }
            $item = isset($entry['source'])
                ? $entry['source']::getNavigationItems()[0]
                : NavigationItem::make($entry['label'])->url($this->url($key))->icon($entry['icon'])
                    ->isActiveWhen(fn () => request()->routeIs(str_replace('.index', '.*', $entry['route'])));
            $item->extraAttributes(['data-gq-nav-key' => $key]);
            if ($key === 'StudentDepartureResource') {
                $item->label('Arsip Santri');
            } elseif ($key === 'TasmiRecordResource') {
                $item->label('Hasil Tasmi');
            }
            if ($entry['group'] === null) {
                $builder->item($item);
            } else {
                $groups[$entry['group']][] = $item;
            }
        }

        foreach ($groups as $label => $items) {
            if ($items === []) {
                continue;
            }
            $active = collect($items)->contains(fn (NavigationItem $item) => $item->isActive());
            $collapsed = ! $active && ! in_array($label, ['Khusus Admin', 'Rapor'], true);
            $builder->group(NavigationGroup::make($label)->items($items)
                ->collapsed($collapsed)->collapsible()
                ->extraSidebarAttributes(['data-gq-nav-group' => $label, 'data-gq-nav-default-collapsed' => $collapsed ? 'true' : 'false']));
        }

        return $builder;
    }
}
