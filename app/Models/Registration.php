<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'area_id',
    'registered_by',
    'reviewed_by',
    'name',
    'nik',
    'phone',
    'email',
    'ktp_full_address',
    'installation_full_address',
    'province',
    'city',
    'district',
    'village',
    'rt',
    'rw',
    'postal_code',
    'latitude',
    'longitude',
    'ktp_original_file_path',
    'ktp_processed_file_path',
    'ktp_ocr_raw_text',
    'ktp_ocr_parsed_data',
    'ktp_verified_at',
    'package',
    'status',
    'technician_notes',
    'admin_notes',
    'submitted_at',
    'reviewed_at',
])]
class Registration extends Model
{
    public const PACKAGES = [
        '10MB',
        '15MB',
        '25MB',
        '30MB',
        '35MB',
        '50MB',
        '100MB',
        '200MB',
    ];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_NEEDS_REVISION = 'needs_revision';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'ktp_ocr_parsed_data' => 'array',
            'ktp_verified_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
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

    public function canBeSubmitted(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_NEEDS_REVISION], true);
    }

    /**
     * @return array{
     *     complete: bool,
     *     missing: array<int, string>,
     *     has_customer: bool,
     *     has_address: bool,
     *     has_ktp: bool,
     *     has_gps: bool,
     *     has_evidence: bool
     * }
     */
    public function technicianReadiness(): array
    {
        $this->loadMissing('evidence');

        $hasKtp = filled($this->ktp_original_file_path) || filled($this->ktp_processed_file_path);
        $hasGps = filled($this->latitude) && filled($this->longitude);
        $hasEvidence = $this->evidence->isNotEmpty();
        $hasCustomer = filled($this->name) && filled($this->nik) && filled($this->phone);
        $hasAddress = filled($this->installation_full_address);

        $missing = array_values(array_filter([
            blank($this->name) ? 'Nama pelanggan' : null,
            blank($this->nik) ? 'NIK' : null,
            blank($this->phone) ? 'Nomor telepon' : null,
            blank($this->package) ? 'Paket' : null,
            ! $hasAddress ? 'Alamat instalasi' : null,
            ! $hasGps ? 'Koordinat GPS' : null,
            ! $hasKtp ? 'Foto KTP' : null,
        ]));

        return [
            'complete' => $missing === [],
            'missing' => $missing,
            'has_customer' => $hasCustomer,
            'has_address' => $hasAddress,
            'has_ktp' => $hasKtp,
            'has_gps' => $hasGps,
            'has_evidence' => $hasEvidence,
        ];
    }

    public static function packageOptions(): array
    {
        return array_combine(self::PACKAGES, self::PACKAGES);
    }

    /**
     * Return the registration data in the shape expected by the e-billing
     * customer form. The local app still stores packages as a stable package
     * mapping value until real e-billing package records are available here.
     *
     * @return array<string, mixed>
     */
    public function toEbillingCustomerPayload(): array
    {
        return [
            'name' => $this->name,
            'phone' => $this->phone,
            'nik' => $this->nik,
            'address' => $this->installation_full_address,
            'area_id' => $this->area_id,
            'package_id' => $this->package,
            'geo_lat' => $this->latitude,
            'geo_long' => $this->longitude,
            'ktp_photo_url' => $this->ktp_processed_file_path ?: $this->ktp_original_file_path,
        ];
    }
}
