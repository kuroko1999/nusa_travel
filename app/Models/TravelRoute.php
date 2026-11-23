<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TravelRoute extends Model
{
    protected $fillable = ['origin', 'destination', 'duration_minutes', 'base_fare', 'active'];

    protected $table = 'routes';

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
