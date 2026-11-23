<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingPassenger extends Model
{
    public $timestamps = false;

    protected $fillable = ['booking_id', 'vehicle_seat_id', 'name', 'identity_number', 'checked_in_at'];

    public function seat()
    {
        return $this->belongsTo(VehicleSeat::class, 'vehicle_seat_id');
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    protected function casts(): array
    {
        return ['checked_in_at' => 'datetime'];
    }
}
