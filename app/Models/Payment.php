<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['booking_id', 'method', 'status', 'amount', 'gateway_order_id', 'gateway_transaction_id', 'redirect_url', 'proof_path', 'rejection_reason', 'verified_at'];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }
}
