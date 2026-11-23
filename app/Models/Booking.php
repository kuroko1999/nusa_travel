<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = ['code', 'user_id', 'schedule_id', 'status', 'payment_status', 'pickup_address', 'dropoff_address', 'phone', 'notes', 'passenger_count', 'fare_snapshot', 'pickup_fee', 'dropoff_fee', 'discount', 'total', 'expires_at', 'paid_at'];

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
