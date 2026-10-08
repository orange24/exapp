<?php

use App\Http\Controllers\Api\CardReaderController;
use App\Http\Controllers\Api\OcrController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/ocr/passport', [OcrController::class, 'passport'])->name('api.ocr.passport');
});

// เครื่องอ่านบัตรประชาชนที่เคาน์เตอร์ — ยืนยันตัวด้วย token ประจำเครื่อง ไม่ใช่ session
// agent เป็นโปรแกรมที่รันอยู่ที่สาขา ไม่ได้อยู่หลังการล็อกอินของใคร
Route::middleware('card-reader')->prefix('card-reader')->group(function () {
    Route::post('/heartbeat', [CardReaderController::class, 'heartbeat'])->name('api.card-reader.heartbeat');
    Route::post('/read', [CardReaderController::class, 'read'])->name('api.card-reader.read');
    Route::post('/passport-progress', [CardReaderController::class, 'passportProgress'])->name('api.card-reader.passport-progress');
    Route::post('/passport', [CardReaderController::class, 'passport'])->name('api.card-reader.passport');
});
