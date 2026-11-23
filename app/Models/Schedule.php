<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    protected $fillable = ['route_id', 'vehicle_id', 'driver_id', 'departs_at', 'arrives_at', 'fare', 'status'];

    public function route()
    {
        return $this->belongsTo(TravelRoute::class, 'route_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function occupiedSeats()
    {
        return $this->hasMany(BookingSeat::class);
    }

    protected function casts(): array
    {
        return ['departs_at' => 'datetime', 'arrives_at' => 'datetime'];
    }
}
