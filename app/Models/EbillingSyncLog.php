<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'registration_id',
    'synced_by',
    'status',
    'request_payload',
    'response_payload',
    'error_message',
    'started_at',
    'finished_at',
])]
class EbillingSyncLog extends Model
{
    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function registration()
    {
        return $this->belongsTo(Registration::class);
    }

    public function syncedBy()
    {
        return $this->belongsTo(User::class, 'synced_by');
    }
}
