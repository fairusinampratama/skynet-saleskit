<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'customer_id', 'assigned_to', 'task_type', 'status', 'technician_notes', 'photo_evidence',
])]
class Task extends Model
{
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function technician()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
