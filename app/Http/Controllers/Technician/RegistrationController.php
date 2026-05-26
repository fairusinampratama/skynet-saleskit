<?php

namespace App\Http\Controllers\Technician;

use App\Contracts\OcrService;
use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Customer;
use App\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RegistrationController extends Controller
{
    public function index()
    {
        $registrations = Registration::with(['customer', 'area'])
            ->where('registered_by', auth()->id())
            ->latest()
            ->paginate(15);

        return view('technician.registrations.index', compact('registrations'));
    }

    public function create()
    {
        return view('technician.registrations.form', [
            'registration' => null,
            'areas' => Area::query()->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, OcrService $ocrService)
    {
        $submit = $request->input('action') === 'submit';
        $validated = $this->validatedData($request, $submit);

        $registration = DB::transaction(function () use ($request, $validated, $submit, $ocrService) {
            $customer = Customer::create($this->customerPayload($validated));

            $registration = Registration::create([
                'customer_id' => $customer->id,
                'area_id' => $validated['area_id'] ?? null,
                'registered_by' => auth()->id(),
                'status' => $submit ? Registration::STATUS_SUBMITTED : Registration::STATUS_DRAFT,
                'technician_notes' => $validated['technician_notes'] ?? null,
                'submitted_at' => $submit ? now() : null,
            ]);

            $this->persistAddresses($customer, $validated);
            $this->persistKtpDocument($request, $customer, $ocrService);
            $this->persistEvidence($request, $registration);

            return $registration;
        });

        return redirect()
            ->route('technician.registrations.show', $registration)
            ->with('status', $submit ? 'Registration submitted for admin review.' : 'Draft saved.');
    }

    public function show(Registration $registration)
    {
        $this->authorizeTechnicianRegistration($registration);

        $registration->load(['customer.ktpDocument', 'customer.addresses', 'area', 'evidence']);

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
            'registration' => $registration->load(['customer.ktpDocument', 'customer.addresses', 'area', 'evidence']),
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
            $registration->customer->update($this->customerPayload($validated));

            $registration->update([
                'area_id' => $validated['area_id'] ?? null,
                'status' => $submit ? Registration::STATUS_SUBMITTED : $registration->status,
                'technician_notes' => $validated['technician_notes'] ?? null,
                'submitted_at' => $submit ? now() : $registration->submitted_at,
            ]);

            $this->persistAddresses($registration->customer, $validated);
            $this->persistKtpDocument($request, $registration->customer, $ocrService);
            $this->persistEvidence($request, $registration);
        });

        return redirect()
            ->route('technician.registrations.show', $registration)
            ->with('status', $submit ? 'Registration submitted for admin review.' : 'Draft updated.');
    }

    private function validatedData(Request $request, bool $submit, ?Registration $registration = null): array
    {
        $required = $submit ? 'required' : 'nullable';
        $hasKtp = (bool) $registration?->customer?->ktpDocument?->original_file_path;
        $hasEvidence = (bool) $registration?->evidence?->count();

        return $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'nik' => [$required, 'string', 'max:32'],
            'phone' => [$required, 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'area_id' => [$required, Rule::exists('areas', 'id')->where('active', true)],
            'ktp_full_address' => [$required, 'string'],
            'installation_full_address' => [$required, 'string'],
            'province' => [$required, 'string', 'max:255'],
            'city' => [$required, 'string', 'max:255'],
            'district' => [$required, 'string', 'max:255'],
            'village' => [$required, 'string', 'max:255'],
            'rt' => ['nullable', 'string', 'max:10'],
            'rw' => ['nullable', 'string', 'max:10'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'latitude' => [$required, 'numeric'],
            'longitude' => [$required, 'numeric'],
            'technician_notes' => ['nullable', 'string'],
            'ktp_image' => [$submit && ! $hasKtp ? 'required' : 'nullable', 'image'],
            'processed_ktp_image' => ['nullable', 'string'],
            'location_photo' => [$submit && ! $hasEvidence ? 'required' : 'nullable', 'image'],
        ]);
    }

    private function customerPayload(array $validated): array
    {
        return [
            'name' => $validated['name'] ?? 'Draft customer',
            'nik' => $validated['nik'] ?? null,
            'phone' => $validated['phone'] ?? '-',
            'email' => $validated['email'] ?? null,
            'status' => 'active',
            'province' => $validated['province'] ?? '',
            'city' => $validated['city'] ?? '',
            'district' => $validated['district'] ?? '',
            'village' => $validated['village'] ?? '',
            'zip_code' => $validated['postal_code'] ?? '',
            'rt' => $validated['rt'] ?? null,
            'rw' => $validated['rw'] ?? null,
            'full_address' => $validated['installation_full_address'] ?? '',
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
        ];
    }

    private function persistAddresses(Customer $customer, array $validated): void
    {
        foreach (['ktp', 'installation'] as $type) {
            $customer->addresses()->updateOrCreate(
                ['address_type' => $type],
                [
                    'province' => $validated['province'] ?? null,
                    'city' => $validated['city'] ?? null,
                    'district' => $validated['district'] ?? null,
                    'village' => $validated['village'] ?? null,
                    'rt' => $validated['rt'] ?? null,
                    'rw' => $validated['rw'] ?? null,
                    'postal_code' => $validated['postal_code'] ?? null,
                    'full_address' => $validated[$type.'_full_address'] ?? null,
                    'latitude' => $type === 'installation' ? ($validated['latitude'] ?? null) : null,
                    'longitude' => $type === 'installation' ? ($validated['longitude'] ?? null) : null,
                ],
            );
        }
    }

    private function persistKtpDocument(Request $request, Customer $customer, OcrService $ocrService): void
    {
        if (! $request->hasFile('ktp_image') && blank($request->input('processed_ktp_image'))) {
            return;
        }

        $document = $customer->ktpDocument()->firstOrNew(['document_type' => 'ktp']);

        if ($request->hasFile('ktp_image')) {
            $document->original_file_path = $request->file('ktp_image')->store('ktp/original', 'public');
            $document->processed_file_path = $this->storeProcessedKtpImage($document->original_file_path);
        }

        if (! $document->processed_file_path && filled($request->input('processed_ktp_image'))) {
            $document->processed_file_path = $this->storeBase64Image($request->input('processed_ktp_image'), 'ktp/processed');
        }

        $ocr = $document->processed_file_path
            ? $ocrService->readKtp($document->processed_file_path)
            : ['raw_text' => null, 'parsed' => []];

        $document->fill([
            'ocr_raw_text' => $ocr['raw_text'],
            'ocr_parsed_data' => $ocr['parsed'],
            'verified_at' => now(),
        ])->save();
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
        $data = preg_replace('#^data:image/\w+;base64,#i', '', $dataUrl);
        $path = $directory.'/'.Str::uuid().'.jpg';

        Storage::disk('public')->put($path, base64_decode($data));

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
