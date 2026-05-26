<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'customer_id',
    'document_type',
    'original_file_path',
    'processed_file_path',
    'ocr_raw_text',
    'ocr_parsed_data',
    'verified_at',
])]
class CustomerDocument extends Model
{
    protected function casts(): array
    {
        return [
            'ocr_parsed_data' => 'array',
            'verified_at' => 'datetime',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
