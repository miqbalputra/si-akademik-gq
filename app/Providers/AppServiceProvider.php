<?php

namespace App\Providers;

use App\Models\DiniyyahScore;
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

        \Filament\Forms\Components\Field::configureUsing(function (\Filament\Forms\Components\Field $field): void {
            $field->translateLabel();
        });
        \Filament\Tables\Columns\Column::configureUsing(function (\Filament\Tables\Columns\Column $column): void {
            $column->translateLabel();
        });
    }
}
