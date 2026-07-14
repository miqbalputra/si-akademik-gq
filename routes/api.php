<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\N8nIntegrationController;

Route::prefix('v1/diniyyah/journals')->group(function () {
    Route::get('/missing-reminders', [N8nIntegrationController::class, 'getMissingDiniyyahJournals']);
});
