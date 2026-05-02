<?php

use App\Http\Controllers\Api\OcrController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/ocr/passport', [OcrController::class, 'passport'])->name('api.ocr.passport');
});
