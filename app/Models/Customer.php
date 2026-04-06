<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'is_interested', 'reason_category', 'reason_description', 'name', 'phone', 'email', 'province', 'city',
    'district', 'village', 'zip_code', 'rt', 'rw', 'full_address',
    'notes', 'latitude', 'longitude', 'photo_evidence'
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
}
