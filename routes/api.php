<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\N8nIntegrationController;
use App\Http\Controllers\Api\RppSyncWebhookController;

Route::prefix('v1/diniyyah/journals')->group(function () {
    Route::get('/missing-reminders', [N8nIntegrationController::class, 'getMissingDiniyyahJournals']);
});

Route::post('/integrations/rpp/webhook', RppSyncWebhookController::class)->middleware('throttle:60,1');
