<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    protected $fillable = ['bank', 'number', 'holder', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
