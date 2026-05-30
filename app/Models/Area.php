<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'code',
    'name',
    'province',
    'city',
    'district',
    'village',
    'coverage_notes',
    'active',
])]
class Area extends Model
{
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function registrations()
    {
        return $this->hasMany(Registration::class);
    }
}
