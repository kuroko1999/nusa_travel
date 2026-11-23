<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingSeat extends Model
{
    public $timestamps = false;

    protected $fillable = ['booking_id', 'schedule_id', 'vehicle_seat_id'];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
