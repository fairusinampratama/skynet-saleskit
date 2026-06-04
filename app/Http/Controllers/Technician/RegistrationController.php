<?php

namespace App\Http\Controllers\Technician;

use App\Contracts\OcrService;
use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RegistrationController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        $search = trim((string) $request->query('q', ''));
        $statuses = [
            Registration::STATUS_DRAFT,
            Registration::STATUS_SUBMITTED,
            Registration::STATUS_NEEDS_REVISION,
            Registration::STATUS_APPROVED,
            Registration::STATUS_CANCELLED,
        ];
        $activeStatus = in_array($status, $statuses, true) ? $status : null;

        $registrations = Registration::with(['area', 'evidence'])
            ->where('registered_by', auth()->id())
            ->when($activeStatus, fn ($query) => $query->where('status', $activeStatus))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('nik', 'like', "%{$search}%")
                        ->orWhereHas('area', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('technician.registrations.index', compact('registrations', 'statuses', 'activeStatus', 'search'));
    }

    public function create()
    {
        return view('technician.registrations.form', [
            'registration' => null,
            'areas' => Area::query()->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function scanKtp(Request $request, OcrService $ocrService)
    {
        $validated = $request->validate([
            'processed_ktp_image' => ['required', 'string'],
        ]);

        $path = $this->storeBase64Image($validated['processed_ktp_image'], 'ktp/scans');

        try {
            $ocr = $ocrService->readKtp($path);
        } finally {
            Storage::disk('public')->delete($path);
        }

        $parsed = $ocr['parsed'] ?? [];
        $error = $parsed['ocr_error'] ?? null;

        return response()->json([
            'raw_text' => $ocr['raw_text'] ?? null,
            'raw_text_path' => $ocr['raw_text_path'] ?? null,
            'parsed' => $parsed,
            'confidence' => $ocr['confidence'] ?? [
                'fields' => [],
                'overall' => 'low',
                'status' => 'manual_entry_required',
                'warnings' => [],
            ],
            'warnings' => $ocr['warnings'] ?? [],
            'error' => $error ?: (empty($parsed) ? 'Tidak ada field KTP yang terbaca.' : null),
        ]);
    }

    public function store(Request $request, OcrService $ocrService)
    {
        $submit = $request->input('action') === 'submit';
        $validated = $this->validatedData($request, $submit);

        $registration = DB::transaction(function () use ($request, $validated, $submit, $ocrService) {
            $registration = Registration::create(array_merge($this->registrationPayload($validated), [
                'area_id' => $validated['area_id'] ?? null,
                'registered_by' => auth()->id(),
                'status' => $submit ? Registration::STATUS_SUBMITTED : Registration::STATUS_DRAFT,
                'technician_notes' => $validated['technician_notes'] ?? null,
                'submitted_at' => $submit ? now() : null,
            ]));

            $this->persistKtpDocument($request, $registration, $ocrService);
            $this->persistEvidence($request, $registration);

            return $registration;
        });

        return redirect()
            ->route('technician.registrations.show', $registration)
            ->with('status', $submit ? __('ui.common.submitted_for_review') : __('ui.common.draft_saved'));
    }

    public function show(Registration $registration)
    {
        $this->authorizeTechnicianRegistration($registration);

        $registration->load(['area', 'evidence']);

        return view('technician.registrations.show', compact('registration'));
    }

    public function edit(Registration $registration)
    {
        $this->authorizeTechnicianRegistration($registration);

        abort_unless(in_array($registration->status, [
            Registration::STATUS_DRAFT,
            Registration::STATUS_NEEDS_REVISION,
        ], true), 403);

        return view('technician.registrations.form', [
            'registration' => $registration->load(['area', 'evidence']),
            'areas' => Area::query()->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Registration $registration, OcrService $ocrService)
    {
        $this->authorizeTechnicianRegistration($registration);

        abort_unless(in_array($registration->status, [
            Registration::STATUS_DRAFT,
            Registration::STATUS_NEEDS_REVISION,
        ], true), 403);

        $submit = $request->input('action') === 'submit';
        $validated = $this->validatedData($request, $submit, $registration);

        DB::transaction(function () use ($request, $registration, $validated, $submit, $ocrService) {
            $registration->update(array_merge($this->registrationPayload($validated), [
                'area_id' => $validated['area_id'] ?? null,
                'status' => $submit ? Registration::STATUS_SUBMITTED : $registration->status,
                'technician_notes' => $validated['technician_notes'] ?? null,
                'submitted_at' => $submit ? now() : $registration->submitted_at,
            ]));

            $this->persistKtpDocument($request, $registration, $ocrService);
            $this->persistEvidence($request, $registration);
        });

        return redirect()
            ->route('technician.registrations.show', $registration)
            ->with('status', $submit ? __('ui.common.submitted_for_review') : 'Draf diperbarui.');
    }

    private function validatedData(Request $request, bool $submit, ?Registration $registration = null): array
    {
        $required = $submit ? 'required' : 'nullable';
        $hasKtp = (bool) ($registration?->ktp_original_file_path || $registration?->ktp_processed_file_path);

        return $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'nik' => [$required, 'string', 'max:32'],
            'phone' => [$required, 'string', 'max:30'],
            'package' => [$required, Rule::in(Registration::PACKAGES)],
            'area_id' => [$required, Rule::exists('areas', 'id')->where('active', true)],
            'ktp_full_address' => ['nullable', 'string'],
            'installation_full_address' => [$required, 'string'],
            'latitude' => [$required, 'numeric'],
            'longitude' => [$required, 'numeric'],
            'technician_notes' => ['nullable', 'string'],
            'ktp_image' => [
                $submit && ! $hasKtp ? 'required_without:processed_ktp_image' : 'nullable',
                'image',
            ],
            'processed_ktp_image' => [
                $submit && ! $hasKtp ? 'required_without:ktp_image' : 'nullable',
                'string',
            ],
            'ocr_field_sources' => ['nullable', 'json'],
            'location_photo' => ['nullable', 'image'],
        ]);
    }

    private function registrationPayload(array $validated): array
    {
        return [
            'name' => $validated['name'] ?? 'Pelanggan draf',
            'nik' => $validated['nik'] ?? null,
            'phone' => $validated['phone'] ?? '-',
            'package' => $validated['package'] ?? null,
            'ktp_full_address' => $validated['ktp_full_address'] ?? null,
            'installation_full_address' => $validated['installation_full_address'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
        ];
    }

    private function persistKtpDocument(Request $request, Registration $registration, OcrService $ocrService): void
    {
        if (! $request->hasFile('ktp_image') && blank($request->input('processed_ktp_image'))) {
            return;
        }

        $originalPath = $registration->ktp_original_file_path;
        $processedPath = $registration->ktp_processed_file_path;

        if ($request->hasFile('ktp_image')) {
            $originalPath = $request->file('ktp_image')->store('ktp/original', 'public');
        }

        if (filled($request->input('processed_ktp_image'))) {
            $processedPath = $this->storeBase64Image($request->input('processed_ktp_image'), 'ktp/processed');
        } elseif ($request->hasFile('ktp_image')) {
            $processedPath = $this->storeProcessedKtpImage($originalPath);
        }

        $ocr = $processedPath
            ? $ocrService->readKtp($processedPath)
            : ['raw_text' => null, 'parsed' => []];

        $registration->update([
            'ktp_original_file_path' => $originalPath,
            'ktp_processed_file_path' => $processedPath,
            'ktp_ocr_raw_text' => $ocr['raw_text'],
            'ktp_ocr_parsed_data' => [
                'parsed' => $ocr['parsed'] ?? [],
                'confidence' => $ocr['confidence'] ?? null,
                'warnings' => $ocr['warnings'] ?? [],
                'variants' => $ocr['variants'] ?? [],
                'field_sources' => json_decode((string) $request->input('ocr_field_sources', '{}'), true) ?: [],
            ],
            'ktp_verified_at' => now(),
        ]);
    }

    private function persistEvidence(Request $request, Registration $registration): void
    {
        if (! $request->hasFile('location_photo')) {
            return;
        }

        $registration->evidence()->create([
            'evidence_type' => 'location_photo',
            'file_path' => $request->file('location_photo')->store('registration-evidence', 'public'),
        ]);
    }

    private function storeBase64Image(string $dataUrl, string $directory): string
    {
        if (! preg_match('#^data:image/(?:jpeg|jpg|png|webp);base64,([A-Za-z0-9+/=\s]+)$#i', $dataUrl, $matches)) {
            throw ValidationException::withMessages([
                'processed_ktp_image' => 'Hasil foto KTP harus berupa gambar yang valid.',
            ]);
        }

        $contents = base64_decode(preg_replace('/\s+/', '', $matches[1]), true);

        $image = $contents === false ? false : @imagecreatefromstring($contents);

        if (! $image) {
            throw ValidationException::withMessages([
                'processed_ktp_image' => 'Hasil foto KTP tidak dapat dibaca.',
            ]);
        }

        imagedestroy($image);

        $path = $directory.'/'.Str::uuid().'.jpg';

        Storage::disk('public')->put($path, $contents);

        return $path;
    }

    private function storeProcessedKtpImage(string $originalPath): string
    {
        $disk = Storage::disk('public');
        $sourcePath = $disk->path($originalPath);
        $imageData = file_get_contents($sourcePath);
        $image = $imageData ? imagecreatefromstring($imageData) : false;

        if (! $image) {
            return $originalPath;
        }

        $image = $this->orientImage($image, $sourcePath);
        $width = imagesx($image);
        $height = imagesy($image);
        $targetWidth = min(1800, $width);
        $targetHeight = (int) round($height * ($targetWidth / $width));
        $processed = imagecreatetruecolor($targetWidth, $targetHeight);

        imagecopyresampled($processed, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagefilter($processed, IMG_FILTER_GRAYSCALE);
        imagefilter($processed, IMG_FILTER_CONTRAST, -18);
        imagefilter($processed, IMG_FILTER_BRIGHTNESS, 8);

        $processedPath = 'ktp/processed/'.Str::uuid().'.jpg';

        ob_start();
        imagejpeg($processed, null, 88);
        $contents = ob_get_clean();

        imagedestroy($image);
        imagedestroy($processed);

        $disk->put($processedPath, $contents);

        return $processedPath;
    }

    private function orientImage(\GdImage $image, string $sourcePath): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($sourcePath);
        $orientation = $exif['Orientation'] ?? null;

        return match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }

    private function authorizeTechnicianRegistration(Registration $registration): void
    {
        abort_unless($registration->registered_by === auth()->id(), 403);
    }
}
