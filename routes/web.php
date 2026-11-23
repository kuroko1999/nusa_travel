<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ManagementController;
use App\Http\Controllers\Admin\OperationalController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/travel', [HomeController::class, 'search'])->name('travel.search');
Route::get('/jadwal/{schedule}', [HomeController::class, 'show'])->name('schedules.show');
Route::get('/informasi/{type}', [HomeController::class, 'information'])->name('information');
Route::get('/cek-booking', [BookingController::class, 'check'])->name('booking.check');
Route::post('/cek-booking', [BookingController::class, 'recover'])->middleware('throttle:6,1')->name('booking.recover');
Route::get('/verifikasi/tiket/{code}', [DocumentController::class, 'verifyTicket'])->name('verify.ticket');
Route::get('/verifikasi/faktur/{number}', [DocumentController::class, 'verifyInvoice'])->name('verify.invoice');
Route::post('/payments/midtrans/webhook', [PaymentController::class, 'webhook'])->name('payments.webhook');
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::get('/daftar', [AuthController::class, 'registerForm'])->name('register');
    Route::post('/daftar', [AuthController::class, 'register'])->middleware('throttle:5,1');
});
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/akun/dokumen/{type}', [BookingController::class, 'documents'])->name('customer.documents');
    Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profil', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/akun', [BookingController::class, 'index'])->name('customer.dashboard');
    Route::post('/pesanan/{booking}/ulasan', [ReviewController::class, 'store'])->name('reviews.store');
});
Route::middleware('cache.headers:private;no_store')->group(function () {
    Route::post('/pesanan', [BookingController::class, 'store'])->middleware('throttle:20,1')->name('bookings.store');
    Route::get('/pesanan/{booking}', [BookingController::class, 'show'])->name('bookings.show');
    Route::post('/pesanan/{booking}/batal', [BookingController::class, 'cancel'])->name('bookings.cancel');
    Route::post('/pesanan/{booking}/bayar', [PaymentController::class, 'choose'])->name('payments.choose');
    Route::post('/pembayaran/{payment}/bukti', [PaymentController::class, 'proof'])->name('payments.proof');
    Route::get('/tiket/{booking}', [DocumentController::class, 'ticket'])->name('tickets.show');
    Route::get('/tiket/{booking}/pdf', [DocumentController::class, 'ticketPdf'])->name('tickets.pdf');
    Route::get('/faktur/{invoice}', [DocumentController::class, 'invoice'])->name('invoices.show');
    Route::get('/faktur/{invoice}/pdf', [DocumentController::class, 'invoicePdf'])->name('invoices.pdf');
});
Route::prefix('admin')->name('admin.')->middleware(['auth', 'super_admin'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/data/{type}', [OperationalController::class, 'index'])->name('resources.index');
    Route::get('/data/{type}/buat', [OperationalController::class, 'create'])->name('resources.create');
    Route::post('/data/{type}', [OperationalController::class, 'store'])->name('resources.store');
    Route::get('/data/{type}/{id}/ubah', [OperationalController::class, 'edit'])->name('resources.edit');
    Route::put('/data/{type}/{id}', [OperationalController::class, 'update'])->name('resources.update');
    Route::delete('/data/{type}/{id}', [OperationalController::class, 'destroy'])->name('resources.destroy');
    Route::get('/kendaraan/{id}/kursi', [OperationalController::class, 'seatForm'])->name('seats.edit');
    Route::put('/kendaraan/{id}/kursi', [OperationalController::class, 'saveSeats'])->name('seats.update');
    Route::get('/ulasan', [ReviewController::class, 'index'])->name('reviews');
    Route::post('/ulasan/{review}/tampilkan', [ReviewController::class, 'approve'])->name('reviews.approve');
    Route::post('/ulasan/{review}/sembunyikan', [ReviewController::class, 'hide'])->name('reviews.hide');
    Route::get('/laporan', [ReportController::class, 'index'])->name('reports');
    Route::get('/laporan/csv', [ReportController::class, 'csv'])->name('reports.csv');
    Route::get('/jadwal/{schedule}/manifest', [ReportController::class, 'manifest'])->name('manifest');
    Route::get('/pemesanan', [ManagementController::class, 'bookings'])->name('bookings');
    Route::get('/pembayaran', [ManagementController::class, 'payments'])->name('payments');
    Route::post('/pembayaran/{payment}/setujui', [ManagementController::class, 'approve'])->name('payments.approve');
    Route::post('/pembayaran/{payment}/tolak', [ManagementController::class, 'reject'])->name('payments.reject');
    Route::get('/pembayaran/{payment}/bukti', [ManagementController::class, 'proof'])->name('payments.proof');
    Route::get('/faktur', [ManagementController::class, 'invoices'])->name('invoices');
    Route::get('/pelanggan', [ManagementController::class, 'customers'])->name('customers');
    Route::get('/pengaturan', [ManagementController::class, 'settings'])->name('settings');
    Route::post('/pengaturan', [ManagementController::class, 'saveSettings'])->name('settings.save');
    Route::post('/pemesanan/{booking}/status',[ManagementController::class, 'status'])->name('bookings.status');
    Route::post('/pemesanan/{booking}/checkin',[ManagementController::class, 'checkin'])->name('checkin');
});
