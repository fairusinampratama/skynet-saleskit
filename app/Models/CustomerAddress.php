<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'customer_id',
    'address_type',
    'province',
    'city',
    'district',
    'village',
    'rt',
    'rw',
    'postal_code',
    'full_address',
    'latitude',
    'longitude',
])]
class CustomerAddress extends Model
{
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
