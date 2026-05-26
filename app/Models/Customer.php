<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'is_interested', 'reason_category', 'reason_description', 'name', 'nik', 'phone', 'email', 'status', 'ebilling_customer_id', 'province', 'city',
    'district', 'village', 'zip_code', 'rt', 'rw', 'full_address',
    'notes', 'latitude', 'longitude', 'photo_evidence',
])]
class Customer extends Model
{
    protected function casts(): array
    {
        return [
            'is_interested' => 'boolean',
        ];
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function registrations()
    {
        return $this->hasMany(Registration::class);
    }

    public function documents()
    {
        return $this->hasMany(CustomerDocument::class);
    }

    public function addresses()
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function ktpDocument()
    {
        return $this->hasOne(CustomerDocument::class)->where('document_type', 'ktp');
    }

    public function installationAddress()
    {
        return $this->hasOne(CustomerAddress::class)->where('address_type', 'installation');
    }
}
