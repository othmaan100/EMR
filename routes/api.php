<?php

use App\Http\Controllers\Api\LabResultController;
use App\Http\Controllers\OnlinePaymentController;
use Illuminate\Support\Facades\Route;

/*
| Machine-to-machine endpoints (no session, no CSRF). Each authenticates
| itself: analysers with a bearer token, gateways with a signed webhook.
*/
Route::prefix('v1')->group(function () {
    Route::post('lab/results', LabResultController::class)->middleware('throttle:240,1')->name('api.lab.results');
    Route::post('payments/webhook/{gateway}', [OnlinePaymentController::class, 'webhook'])->middleware('throttle:120,1')->name('api.payments.webhook');
});
