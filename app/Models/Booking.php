<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class Booking extends Model
{
    protected $fillable = ['code', 'user_id', 'guest_key_hash', 'schedule_id', 'status', 'payment_status', 'pickup_address', 'dropoff_address', 'phone', 'notes', 'passenger_count', 'fare_snapshot', 'pickup_fee', 'dropoff_fee', 'discount', 'total', 'expires_at', 'paid_at'];

    protected $hidden = ['guest_key_hash'];

    public function grantGuestAccess(Request $request): void
    {
        $request->session()->regenerate(true);
        $request->session()->put('guest_bookings.'.$this->id, now()->addHours(24)->timestamp);
    }

    public function hasGuestAccess(Request $request): bool
    {
        return $this->user_id === null
            && $this->guest_key_hash !== null
            && $request->hasSession()
            && $request->session()->get('guest_bookings.'.$this->id, 0) > now()->timestamp;
    }

    public function customerName(): string
    {
        return $this->user?->name ?? $this->passengers->sortBy('id')->first()?->name ?? 'Tamu';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    public function passengers()
    {
        return $this->hasMany(BookingPassenger::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'paid_at' => 'datetime'];
    }
}
