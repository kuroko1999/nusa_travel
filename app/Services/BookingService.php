<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Schedule;
use App\Models\Setting;
use App\Models\VehicleSeat;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function expireDue(): int
    {
        return DB::transaction(function () {
            $expired = Booking::where('status', 'menunggu_pembayaran')->where('expires_at', '<=', now())->lockForUpdate()->get();
            foreach ($expired as $booking) {
                BookingSeat::where('booking_id', $booking->id)->delete();
                $booking->update(['status' => 'kedaluwarsa', 'payment_status' => 'kedaluwarsa']);
                $booking->payments()->where('status', 'pending')->update(['status' => 'kedaluwarsa']);
            }

            return $expired->count();
        }, 3);
    }

    public function reserve(int $userId, array $data): Booking
    {
        $this->expireDue();
        try {
            return DB::transaction(function () use ($userId, $data) {
                $schedule = Schedule::with('vehicle')->lockForUpdate()->findOrFail($data['schedule_id']);
                if ($schedule->status !== 'terjadwal' || $schedule->departs_at->isPast() || $schedule->vehicle->status !== 'aktif') {
                    throw ValidationException::withMessages(['schedule_id' => 'Jadwal tidak tersedia.']);
                }
                $seatIds = array_map('intval', array_column($data['passengers'], 'seat_id'));
                if (count($seatIds) !== count(array_unique($seatIds))) {
                    throw ValidationException::withMessages(['passengers' => 'Setiap penumpang harus memiliki kursi berbeda.']);
                }
                $valid = VehicleSeat::where('vehicle_id', $schedule->vehicle_id)->where('selectable', true)->whereIn('id', $seatIds)->count();
                if ($valid !== count($seatIds) || count($seatIds) > $schedule->vehicle->capacity) {
                    throw ValidationException::withMessages(['passengers' => 'Kursi tidak valid.']);
                }
                $occupied = BookingSeat::where('schedule_id', $schedule->id)->whereIn('vehicle_seat_id', $seatIds)->exists();
                if ($occupied) {
                    throw ValidationException::withMessages(['passengers' => 'Salah satu kursi sudah dipesan.']);
                }
                $pickupFee = (int) (Setting::find('pickup_fee')?->value ?? 0);
                $dropoffFee = (int) (Setting::find('dropoff_fee')?->value ?? 0);
                $count = count($seatIds);
                $booking = Booking::create([
                    'code' => 'TRV-'.now()->format('ymd').'-'.strtoupper(Str::random(7)), 'user_id' => $userId,
                    'schedule_id' => $schedule->id, 'pickup_address' => $data['pickup_address'],
                    'dropoff_address' => $data['dropoff_address'], 'phone' => $data['phone'],
                    'notes' => $data['notes'] ?? null, 'passenger_count' => $count,
                    'fare_snapshot' => $schedule->fare, 'pickup_fee' => $pickupFee, 'dropoff_fee' => $dropoffFee,
                    'total' => $schedule->fare * $count + $pickupFee + $dropoffFee, 'expires_at' => now()->addMinutes(20),
                ]);
                foreach ($data['passengers'] as $person) {
                    $booking->passengers()->create(['vehicle_seat_id' => $person['seat_id'], 'name' => $person['name'], 'identity_number' => $person['identity_number'] ?? null]);
                    BookingSeat::create(['booking_id' => $booking->id, 'schedule_id' => $schedule->id, 'vehicle_seat_id' => $person['seat_id']]);
                }

                return $booking->refresh();
            }, 3);
        } catch (QueryException $e) {
            if (in_array($e->getCode(), ['23000', '19'], true) || ($e->errorInfo[1] ?? null) === 5) {
                throw ValidationException::withMessages(['passengers' => 'Kursi baru saja dipesan. Pilih kursi lain.']);
            }
            throw $e;
        }
    }

    public function cancel(Booking $booking): void
    {
        DB::transaction(function () use ($booking) {
            $booking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if ($booking->payment_status === 'lunas') {
                throw ValidationException::withMessages(['booking' => 'Pesanan lunas memerlukan proses refund oleh Super Admin.']);
            }
            if (! in_array($booking->status, ['menunggu_pembayaran', 'kedaluwarsa'])) {
                throw ValidationException::withMessages(['booking' => 'Pesanan tidak dapat dibatalkan.']);
            }
            $booking->update(['status' => 'dibatalkan']);
            BookingSeat::where('booking_id', $booking->id)->delete();
        });
    }
}
