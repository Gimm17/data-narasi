<?php

use App\Http\Controllers\Api\ReportCallbackController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Routes untuk callback dari Python service dan integrasi eksternal
| Tidak memerlukan authentication (divalidasi via callback secret)
|
*/

/*
|--------------------------------------------------------------------------
| Python Service Callback Routes
|--------------------------------------------------------------------------
*/

// Callback endpoint untuk Python service saat selesai memproses data
Route::post('/v1/reports/{report}/callback', ReportCallbackController::class)
    ->name('api.reports.callback');
