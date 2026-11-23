<?php

use App\Services\BookingService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('bookings:expire', function () {
    $this->info(app(BookingService::class)->expireDue().' pesanan kedaluwarsa.');
});
Schedule::command('bookings:expire')->everyMinute();
