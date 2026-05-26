<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'registration_id',
    'evidence_type',
    'file_path',
    'notes',
])]
class RegistrationEvidence extends Model
{
    public function registration()
    {
        return $this->belongsTo(Registration::class);
    }
}
