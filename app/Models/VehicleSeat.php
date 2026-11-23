<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleSeat extends Model
{
    public $timestamps = false;

    protected $fillable = ['vehicle_id', 'number', 'row', 'column', 'selectable'];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    protected function casts(): array
    {
        return ['selectable' => 'boolean'];
    }
}
