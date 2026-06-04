<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable([
    'code',
    'name',
    'coverage_notes',
    'active',
])]
class Area extends Model
{
    protected static function booted(): void
    {
        static::creating(function (Area $area): void {
            if (blank($area->code)) {
                $area->code = static::generateUniqueCode($area->name);
            }

            if ($area->active === null) {
                $area->active = true;
            }
        });
    }

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

    private static function generateUniqueCode(?string $name): string
    {
        $base = Str::of($name ?: 'area')
            ->slug()
            ->upper()
            ->limit(240, '')
            ->value();

        $base = $base !== '' ? $base : 'AREA';
        $code = $base;
        $suffix = 2;

        while (static::query()->where('code', $code)->exists()) {
            $code = Str::limit($base, 240, '').'-'.$suffix;
            $suffix++;
        }

        return $code;
    }
}
