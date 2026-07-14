<?php

namespace App\Providers;

use App\Models\DiniyyahClassJournal;
use App\Models\DiniyyahClassJournalAbsence;
use App\Models\DiniyyahScore;
use App\Observers\DiniyyahClassJournalAbsenceObserver;
use App\Observers\DiniyyahClassJournalObserver;
use App\Observers\DiniyyahScoreObserver;
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

        \Filament\Forms\Components\Field::configureUsing(function (\Filament\Forms\Components\Field $field): void {
            $field->translateLabel();
        });
        \Filament\Tables\Columns\Column::configureUsing(function (\Filament\Tables\Columns\Column $column): void {
            $column->translateLabel();
        });
    }
}
