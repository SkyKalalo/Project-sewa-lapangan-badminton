<?php

use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\AdminBookingController;
use App\Http\Controllers\Api\OwnerReportController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\BookingRequestController;
use App\Http\Controllers\Api\AdminOperationsController;
use App\Http\Controllers\Api\PaymentMethodController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => ['status' => 'ok']);
Route::get('/jadwal', [ScheduleController::class, 'index']);
Route::get('/metode-pembayaran', [PaymentMethodController::class, 'index']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/booking', [BookingController::class, 'store'])->middleware('throttle:20,1');
Route::get('/booking-terbaru', [BookingController::class, 'recent']);
Route::get('/booking/{kode}', [BookingController::class, 'show']);
Route::post('/booking/{kode}/bukti-dp', [PaymentController::class, 'upload'])->middleware('throttle:10,1');
Route::post('/booking/{kode}/permohonan', [BookingRequestController::class, 'store'])->middleware('throttle:10,1');
Route::middleware('role:Admin')->prefix('admin')->group(function () {
    Route::get('/booking', [BookingController::class, 'adminIndex']);
    Route::post('/booking', [BookingController::class, 'adminStore']);
    Route::get('/pembayaran', [PaymentController::class, 'pending']);
    Route::patch('/pembayaran/{id}/verifikasi', [PaymentController::class, 'verify']);
    Route::post('/booking/{kode}/pelunasan', [PaymentController::class, 'settle']);
    Route::post('/booking/{kode}/batal', [AdminBookingController::class, 'cancel']);
    Route::post('/booking/{kode}/pindah-jadwal', [AdminBookingController::class, 'reschedule']);
    Route::get('/pelanggan/{no_hp}/riwayat', [CustomerController::class, 'history']);
    Route::get('/permohonan', [BookingRequestController::class, 'index']);
    Route::patch('/permohonan/{id}/proses', [BookingRequestController::class, 'process']);
    Route::patch('/lapangan/{id}/status', [AdminOperationsController::class, 'toggleCourt']);
    Route::post('/booking/expire', [AdminOperationsController::class, 'expireBookings']);
});
Route::middleware('role:Pemilik')->prefix('pemilik')->group(function () {
    Route::get('/laporan', [OwnerReportController::class, 'report']);
    Route::get('/laporan/jam-sepi', [OwnerReportController::class, 'quietHours']);
    Route::get('/monitoring-booking', [OwnerReportController::class, 'monitoring']);
    Route::get('/booking', [OwnerReportController::class, 'bookings']);
    Route::get('/aktivitas-admin', [OwnerReportController::class, 'activities']);
    Route::post('/metode-pembayaran', [PaymentMethodController::class, 'store']);
    Route::delete('/metode-pembayaran/{id}', [PaymentMethodController::class, 'destroy']);
});
