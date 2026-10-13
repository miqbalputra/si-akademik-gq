<?php

namespace App\Filament\Widgets;

use App\Services\ClassAttendanceReportService;
use Filament\Widgets\Widget;

class ClassAttendanceReportWidget extends Widget
{
    protected static ?int $sort = 4;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.class-attendance-report';

    public static function canView(): bool
    {
        return auth('admin')->user()?->hasRole('admin') ?? false;
    }

    protected function getViewData(): array
    {
        abort_unless(static::canView(), 403);

        return ['reportOptions' => app(ClassAttendanceReportService::class)->options(auth('admin')->user(), true)];
    }
}
