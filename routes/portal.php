<?php

use App\Http\Controllers\Portal\AuthController;
use App\Http\Controllers\Portal\PortalController;
use Illuminate\Support\Facades\Route;

/*
| Patient portal (guard "patient"). Loaded from routes/web.php.
*/
Route::prefix('portal')->name('portal.')->middleware('portal')->group(function () {
    Route::middleware('guest:patient')->controller(AuthController::class)->group(function () {
        Route::get('login', 'showLogin')->name('login');
        Route::post('login', 'login')->middleware('throttle:10,1');
        Route::get('activate', 'showActivate')->name('activate');
        Route::post('activate', 'activate')->middleware('throttle:10,1');
    });

    Route::middleware('auth:patient')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');

        Route::controller(PortalController::class)->group(function () {
            Route::get('/', 'dashboard')->name('dashboard');
            Route::get('appointments', 'appointments')->name('appointments');
            Route::post('appointments', 'requestAppointment')->name('appointments.request')->middleware('throttle:10,1');
            Route::patch('appointments/{appointment}/cancel', 'cancelAppointment')->name('appointments.cancel');
            Route::get('results', 'results')->name('results');
            Route::get('results/lab/{labOrder}', 'labResult')->name('results.lab');
            Route::get('results/imaging/{imagingOrder}', 'imagingResult')->name('results.imaging');
            Route::get('bills', 'bills')->name('bills');
            Route::post('bills/pay', 'payOnline')->name('bills.pay')->middleware('throttle:10,1');
            Route::get('bills/{bill}', 'invoice')->name('bills.show');
            Route::get('receipts/{payment}', 'receipt')->name('receipts.show');
            Route::get('immunizations', 'immunizations')->name('immunizations');
            Route::get('profile', 'profile')->name('profile');
            Route::put('profile/password', 'password')->name('password');
            Route::post('switch/{patient}', 'switchPatient')->name('switch');
        });
    });
});
