<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $fillable = ['name', 'model', 'plate', 'year', 'capacity', 'photo', 'facilities', 'status', 'notes'];

    public function seats()
    {
        return $this->hasMany(VehicleSeat::class);
    }
}
