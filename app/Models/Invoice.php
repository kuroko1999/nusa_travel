<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = ['booking_id', 'number', 'snapshot', 'status', 'issued_at'];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'issued_at' => 'datetime'];
    }
}
