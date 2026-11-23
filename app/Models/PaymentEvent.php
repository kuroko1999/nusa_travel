<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentEvent extends Model
{
    protected $fillable = ['payment_id', 'event_key', 'status', 'payload'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
