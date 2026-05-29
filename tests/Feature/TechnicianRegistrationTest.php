<?php

namespace Tests\Feature;

use App\Contracts\OcrService;
use App\Models\Area;
use App\Models\CustomerDocument;
use App\Models\Registration;
use App\Models\RegistrationEvidence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TechnicianRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_technician_can_submit_registration_with_jpeg_ktp_and_location_photos(): void
    {
        Storage::fake('public');

        $this->app->bind(OcrService::class, fn () => new class implements OcrService
        {
            public function readKtp(string $processedImagePath): array
            {
                return [
                    'raw_text' => 'Synthetic OCR result',
                    'parsed' => ['source' => 'test'],
                ];
            }
        });

        $technician = User::factory()->create(['role' => 'technician']);
        $area = Area::create([
            'code' => 'MLG-01',
            'name' => 'Malang Test Area',
            'active' => true,
        ]);

        $response = $this
            ->actingAs($technician)
            ->withSession(['_token' => 'test-token'])
            ->post(route('technician.registrations.store'), [
                '_token' => 'test-token',
                'action' => 'submit',
                'name' => 'Test Customer',
                'nik' => '3500000000000000',
                'phone' => '081234567890',
                'area_id' => $area->id,
                'ktp_full_address' => 'Test KTP address',
                'installation_full_address' => 'Test installation address',
                'province' => 'Jawa Timur',
                'city' => 'Kabupaten Malang',
                'district' => 'Pakis',
                'village' => 'Mangliawan',
                'rt' => '001',
                'rw' => '010',
                'latitude' => '-7.96662000',
                'longitude' => '112.63263000',
                'ktp_image' => UploadedFile::fake()->image('ktp.jpg', 1280, 810),
                'processed_ktp_image' => 'data:image/jpeg;base64,'.base64_encode('processed jpeg bytes'),
                'location_photo' => UploadedFile::fake()->image('location.jpg', 1280, 720),
            ]);

        $response->assertSessionHasNoErrors();

        $registration = Registration::query()->sole();
        $document = CustomerDocument::query()->sole();
        $evidence = RegistrationEvidence::query()->sole();

        $response->assertRedirect(route('technician.registrations.show', $registration));

        $this->assertSame(Registration::STATUS_SUBMITTED, $registration->status);
        $this->assertSame($technician->id, $registration->registered_by);
        $this->assertSame('Test Customer', $registration->customer->name);
        $this->assertSame('3500000000000000', $registration->customer->nik);
        $this->assertSame(['source' => 'test'], $document->ocr_parsed_data);

        Storage::disk('public')->assertExists($document->original_file_path);
        Storage::disk('public')->assertExists($document->processed_file_path);
        Storage::disk('public')->assertExists($evidence->file_path);
    }
}
