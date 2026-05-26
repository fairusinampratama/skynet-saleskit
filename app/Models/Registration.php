<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'customer_id',
    'area_id',
    'registered_by',
    'reviewed_by',
    'status',
    'technician_notes',
    'admin_notes',
    'submitted_at',
    'reviewed_at',
    'synced_at',
])]
class Registration extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_NEEDS_REVISION = 'needs_revision';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_SYNCING = 'syncing';

    public const STATUS_SYNCED = 'synced';

    public const STATUS_SYNC_FAILED = 'sync_failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function technician()
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function evidence()
    {
        return $this->hasMany(RegistrationEvidence::class);
    }

    public function syncLogs()
    {
        return $this->hasMany(EbillingSyncLog::class);
    }

    public function canBeSubmitted(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_NEEDS_REVISION], true);
    }
}
