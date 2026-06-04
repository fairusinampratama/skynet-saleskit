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
            Registration::STATUS_APPROVED,
        ];
        $activeStatus = in_array($status, $statuses, true) ? $status : null;

        $registrations = Registration::with(['area'])
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

    public function store(Request $request)
    {
        $submit = $request->input('action') === 'submit';
        $validated = $this->validatedData($request, $submit);

        $registration = DB::transaction(function () use ($request, $validated, $submit) {
            $registration = Registration::create(array_merge($this->registrationPayload($validated), [
                'area_id' => $validated['area_id'] ?? null,
                'registered_by' => auth()->id(),
                'status' => $submit ? Registration::STATUS_SUBMITTED : Registration::STATUS_DRAFT,
                'technician_notes' => $validated['technician_notes'] ?? null,
                'submitted_at' => $submit ? now() : null,
            ]));

            $this->persistKtpDocument($request, $registration);
            $this->persistLocationPhoto($request, $registration);

            return $registration;
        });

        return redirect()
            ->route('technician.registrations.show', $registration)
            ->with('status', $submit ? __('ui.common.submitted_for_review') : __('ui.common.draft_saved'));
    }

    public function show(Registration $registration)
    {
        $this->authorizeTechnicianRegistration($registration);

        $registration->load(['area']);

        return view('technician.registrations.show', compact('registration'));
    }

    public function edit(Registration $registration)
    {
        $this->authorizeTechnicianRegistration($registration);

        abort_unless(in_array($registration->status, [
            Registration::STATUS_DRAFT,
        ], true), 403);

        return view('technician.registrations.form', [
            'registration' => $registration->load(['area']),
            'areas' => Area::query()->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Registration $registration)
    {
        $this->authorizeTechnicianRegistration($registration);

        abort_unless(in_array($registration->status, [
            Registration::STATUS_DRAFT,
        ], true), 403);

        $submit = $request->input('action') === 'submit';
        $validated = $this->validatedData($request, $submit, $registration);

        DB::transaction(function () use ($request, $registration, $validated, $submit) {
            $registration->update(array_merge($this->registrationPayload($validated), [
                'area_id' => $validated['area_id'] ?? null,
                'status' => $submit ? Registration::STATUS_SUBMITTED : $registration->status,
                'technician_notes' => $validated['technician_notes'] ?? null,
                'submitted_at' => $submit ? now() : $registration->submitted_at,
            ]));

            $this->persistKtpDocument($request, $registration);
            $this->persistLocationPhoto($request, $registration);
        });

        return redirect()
            ->route('technician.registrations.show', $registration)
            ->with('status', $submit ? __('ui.common.submitted_for_review') : 'Draf diperbarui.');
    }

    private function validatedData(Request $request, bool $submit, ?Registration $registration = null): array
    {
        $required = $submit ? 'required' : 'nullable';
        $hasKtp = filled($registration?->ktp_photo_path);

        return $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'nik' => [$required, 'digits:16'],
            'phone' => [$required, 'string', 'max:30', 'regex:/^\+?[0-9][0-9\s().-]{7,29}$/'],
            'package' => [$required, Rule::in(Registration::PACKAGES)],
            'area_id' => [$required, Rule::exists('areas', 'id')->where('active', true)],
            'ktp_full_address' => ['nullable', 'string', 'max:2000'],
            'installation_full_address' => [$required, 'string', 'max:2000'],
            'latitude' => [$required, 'numeric', 'between:-90,90'],
            'longitude' => [$required, 'numeric', 'between:-180,180'],
            'technician_notes' => ['nullable', 'string', 'max:2000'],
            'ktp_image' => [
                $submit && ! $hasKtp ? 'required_without:processed_ktp_image' : 'nullable',
                'image',
                'max:20480',
            ],
            'processed_ktp_image' => [
                $submit && ! $hasKtp ? 'required_without:ktp_image' : 'nullable',
                'string',
            ],
            'ocr_field_sources' => ['nullable', 'json'],
            'location_photo' => ['nullable', 'image', 'max:20480'],
            'processed_location_photo' => ['nullable', 'string'],
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

    private function persistKtpDocument(Request $request, Registration $registration): void
    {
        if (! $request->hasFile('ktp_image') && blank($request->input('processed_ktp_image'))) {
            return;
        }

        $oldPath = $registration->ktp_photo_path;
        $photoPath = null;

        if (filled($request->input('processed_ktp_image'))) {
            $photoPath = $this->storeBase64Image($request->input('processed_ktp_image'), 'ktp');
        } elseif ($request->hasFile('ktp_image')) {
            $uploadedPath = $request->file('ktp_image')->store('ktp', 'public');
            $photoPath = $this->storeProcessedKtpImage($uploadedPath);

            if ($photoPath !== $uploadedPath) {
                Storage::disk('public')->delete($uploadedPath);
            }
        }

        if (! $photoPath) {
            return;
        }

        $registration->update(['ktp_photo_path' => $photoPath]);

        if ($oldPath && $oldPath !== $photoPath) {
            Storage::disk('public')->delete($oldPath);
        }
    }

    private function persistLocationPhoto(Request $request, Registration $registration): void
    {
        if (! $request->hasFile('location_photo') && blank($request->input('processed_location_photo'))) {
            return;
        }

        $oldPath = $registration->location_photo_path;
        $path = filled($request->input('processed_location_photo'))
            ? $this->storeBase64Image($request->input('processed_location_photo'), 'registration-location', 'processed_location_photo', 'Foto lokasi')
            : $request->file('location_photo')->store('registration-location', 'public');

        $registration->update(['location_photo_path' => $path]);

        if ($oldPath && $oldPath !== $path) {
            Storage::disk('public')->delete($oldPath);
        }
    }

    private function storeBase64Image(
        string $dataUrl,
        string $directory,
        string $field = 'processed_ktp_image',
        string $label = 'Hasil foto KTP',
    ): string
    {
        if (! preg_match('#^data:image/(?:jpeg|jpg|png|webp);base64,([A-Za-z0-9+/=\s]+)$#i', $dataUrl, $matches)) {
            throw ValidationException::withMessages([
                $field => $label.' harus berupa gambar yang valid.',
            ]);
        }

        $contents = base64_decode(preg_replace('/\s+/', '', $matches[1]), true);

        $image = $contents === false ? false : @imagecreatefromstring($contents);

        if (! $image) {
            throw ValidationException::withMessages([
                $field => $label.' tidak dapat dibaca.',
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

        $processedPath = 'ktp/'.Str::uuid().'.jpg';

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
