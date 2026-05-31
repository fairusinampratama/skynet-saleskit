<?php

namespace Tests\Feature;

use App\Contracts\OcrService;
use App\Models\Area;
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

    public function test_technician_can_submit_registration_with_uploaded_and_processed_ktp_images(): void
    {
        Storage::fake('public');

        $ocr = $this->fakeOcrService();

        $technician = User::factory()->create(['role' => 'technician']);
        $area = $this->createArea();

        $response = $this
            ->actingAs($technician)
            ->withSession(['_token' => 'test-token'])
            ->post(route('technician.registrations.store'), $this->validPayload($area, [
                'ktp_image' => UploadedFile::fake()->image('ktp.jpg', 1280, 810),
                'processed_ktp_image' => $this->processedKtpDataUrl(),
            ]));

        $response->assertSessionHasNoErrors();

        $registration = Registration::query()->sole();
        $evidence = RegistrationEvidence::query()->sole();

        $response->assertRedirect(route('technician.registrations.show', $registration));

        $this->assertSame(Registration::STATUS_SUBMITTED, $registration->status);
        $this->assertSame($technician->id, $registration->registered_by);
        $this->assertSame('Test Customer', $registration->name);
        $this->assertSame('3500000000000000', $registration->nik);
        $this->assertSame(['source' => 'test'], $registration->ktp_ocr_parsed_data['parsed']);
        $this->assertStringStartsWith('ktp/original/', $registration->ktp_original_file_path);
        $this->assertStringStartsWith('ktp/processed/', $registration->ktp_processed_file_path);
        $this->assertSame([$registration->ktp_processed_file_path], $ocr->paths);

        Storage::disk('public')->assertExists($registration->ktp_original_file_path);
        Storage::disk('public')->assertExists($registration->ktp_processed_file_path);
        Storage::disk('public')->assertExists($evidence->file_path);
    }

    public function test_technician_can_submit_registration_with_camera_processed_ktp_only(): void
    {
        Storage::fake('public');

        $ocr = $this->fakeOcrService();
        $technician = User::factory()->create(['role' => 'technician']);
        $area = $this->createArea();

        $response = $this
            ->actingAs($technician)
            ->withSession(['_token' => 'test-token'])
            ->post(route('technician.registrations.store'), $this->validPayload($area, [
                'processed_ktp_image' => $this->processedKtpDataUrl(),
            ]));

        $response->assertSessionHasNoErrors();

        $registration = Registration::query()->sole();

        $this->assertNull($registration->ktp_original_file_path);
        $this->assertStringStartsWith('ktp/processed/', $registration->ktp_processed_file_path);
        $this->assertSame([$registration->ktp_processed_file_path], $ocr->paths);
        Storage::disk('public')->assertExists($registration->ktp_processed_file_path);
    }

    public function test_technician_can_submit_registration_with_uploaded_ktp_only(): void
    {
        Storage::fake('public');

        $ocr = $this->fakeOcrService();
        $technician = User::factory()->create(['role' => 'technician']);
        $area = $this->createArea();

        $response = $this
            ->actingAs($technician)
            ->withSession(['_token' => 'test-token'])
            ->post(route('technician.registrations.store'), $this->validPayload($area, [
                'ktp_image' => UploadedFile::fake()->image('ktp.jpg', 1280, 810),
            ]));

        $response->assertSessionHasNoErrors();

        $registration = Registration::query()->sole();

        $this->assertStringStartsWith('ktp/original/', $registration->ktp_original_file_path);
        $this->assertStringStartsWith('ktp/processed/', $registration->ktp_processed_file_path);
        $this->assertSame([$registration->ktp_processed_file_path], $ocr->paths);
        Storage::disk('public')->assertExists($registration->ktp_original_file_path);
        Storage::disk('public')->assertExists($registration->ktp_processed_file_path);
    }

    public function test_technician_can_resubmit_existing_registration_without_reuploading_ktp(): void
    {
        Storage::fake('public');

        $this->fakeOcrService();
        $technician = User::factory()->create(['role' => 'technician']);
        $area = $this->createArea();

        $this
            ->actingAs($technician)
            ->withSession(['_token' => 'test-token'])
            ->post(route('technician.registrations.store'), $this->validPayload($area, [
                'processed_ktp_image' => $this->processedKtpDataUrl(),
            ]))
            ->assertSessionHasNoErrors();

        $registration = Registration::query()->sole();
        $registration->update(['status' => Registration::STATUS_NEEDS_REVISION]);

        $response = $this
            ->actingAs($technician)
            ->withSession(['_token' => 'test-token'])
            ->put(route('technician.registrations.update', $registration), $this->validPayload($area, [
                'name' => 'Updated Customer',
            ]));

        $response->assertSessionHasNoErrors();

        $registration->refresh();

        $this->assertSame(Registration::STATUS_SUBMITTED, $registration->status);
        $this->assertSame('Updated Customer', $registration->name);
        $this->assertStringStartsWith('ktp/processed/', $registration->ktp_processed_file_path);
    }

    public function test_technician_can_scan_ktp_for_ocr_autofill_data(): void
    {
        Storage::fake('public');

        $ocr = $this->fakeOcrService([
            'nik' => '3500000000000002',
            'name' => 'OCR CUSTOMER',
            'address' => 'OCR KTP ADDRESS',
            'rt' => '002',
            'rw' => '011',
            'village' => 'MANGUNHARJO',
            'district' => 'PAKIS',
        ]);

        $technician = User::factory()->create(['role' => 'technician']);

        $response = $this
            ->actingAs($technician)
            ->postJson(route('technician.registrations.scan-ktp'), [
                'processed_ktp_image' => $this->processedKtpDataUrl(),
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('raw_text', 'Synthetic OCR result')
            ->assertJsonPath('parsed.nik', '3500000000000002')
            ->assertJsonPath('parsed.name', 'OCR CUSTOMER')
            ->assertJsonPath('parsed.address', 'OCR KTP ADDRESS')
            ->assertJsonPath('confidence.overall', 'low')
            ->assertJsonPath('error', null);

        $this->assertCount(1, $ocr->paths);
        $this->assertStringStartsWith('ktp/scans/', $ocr->paths[0]);
        Storage::disk('public')->assertMissing($ocr->paths[0]);
    }

    public function test_guest_cannot_scan_ktp_for_ocr_autofill_data(): void
    {
        $response = $this->postJson(route('technician.registrations.scan-ktp'), [
            'processed_ktp_image' => $this->processedKtpDataUrl(),
        ]);

        $response->assertUnauthorized();
    }

    public function test_scan_ktp_rejects_invalid_processed_image_data(): void
    {
        Storage::fake('public');

        $technician = User::factory()->create(['role' => 'technician']);

        $response = $this
            ->actingAs($technician)
            ->postJson(route('technician.registrations.scan-ktp'), [
                'processed_ktp_image' => 'not-a-data-url',
            ]);

        $response->assertUnprocessable();
    }

    public function test_registration_stores_selected_package(): void
    {
        Storage::fake('public');

        $this->fakeOcrService();
        $technician = User::factory()->create(['role' => 'technician']);
        $area = $this->createArea();

        $this
            ->actingAs($technician)
            ->withSession(['_token' => 'test-token'])
            ->post(route('technician.registrations.store'), $this->validPayload($area, [
                'package' => '50MB',
                'processed_ktp_image' => $this->processedKtpDataUrl(),
            ]))
            ->assertSessionHasNoErrors();

        $registration = Registration::query()->sole();

        $this->assertSame('50MB', $registration->package);
    }

    public function test_technician_can_submit_registration_without_supporting_address_parts(): void
    {
        Storage::fake('public');

        $this->fakeOcrService();
        $technician = User::factory()->create(['role' => 'technician']);
        $area = $this->createArea();
        $payload = $this->validPayload($area, [
            'package' => '25MB',
            'ktp_full_address' => null,
            'province' => null,
            'city' => null,
            'district' => null,
            'village' => null,
            'processed_ktp_image' => $this->processedKtpDataUrl(),
        ]);

        $response = $this
            ->actingAs($technician)
            ->withSession(['_token' => 'test-token'])
            ->post(route('technician.registrations.store'), $payload);

        $response->assertSessionHasNoErrors();

        $registration = Registration::query()->sole();

        $this->assertSame('25MB', $registration->package);
        $this->assertNull($registration->ktp_full_address);
        $this->assertNull($registration->province);
        $this->assertNull($registration->city);
        $this->assertNull($registration->district);
        $this->assertNull($registration->village);
    }

    public function test_registration_maps_to_ebilling_customer_payload(): void
    {
        $area = $this->createArea();
        $technician = User::factory()->create(['role' => 'technician']);

        $registration = Registration::create([
            'area_id' => $area->id,
            'registered_by' => $technician->id,
            'name' => 'Mapped Customer',
            'phone' => '081234567891',
            'nik' => '3500000000000011',
            'package' => '50MB',
            'installation_full_address' => 'Install Address',
            'latitude' => '-7.96662000',
            'longitude' => '112.63263000',
            'ktp_processed_file_path' => 'ktp/processed/test.jpg',
        ]);

        $this->assertSame([
            'name' => 'Mapped Customer',
            'phone' => '081234567891',
            'nik' => '3500000000000011',
            'address' => 'Install Address',
            'area_id' => $area->id,
            'package_id' => '50MB',
            'geo_lat' => '-7.96662000',
            'geo_long' => '112.63263000',
            'ktp_photo_url' => 'ktp/processed/test.jpg',
        ], $registration->toEbillingCustomerPayload());
    }

    public function test_registration_create_form_shows_area_name_without_code(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        $area = $this->createArea([
            'code' => 'SHOULD-NOT-SHOW',
            'name' => 'Name Only Area',
        ]);

        $this
            ->actingAs($technician)
            ->get(route('technician.registrations.create'))
            ->assertOk()
            ->assertSee('Name Only Area')
            ->assertDontSee('SHOULD-NOT-SHOW');
    }

    public function test_technician_registration_index_can_filter_by_status(): void
    {
        Storage::fake('public');

        $this->fakeOcrService();
        $technician = User::factory()->create(['role' => 'technician']);
        $area = $this->createArea();

        $this
            ->actingAs($technician)
            ->withSession(['_token' => 'test-token'])
            ->post(route('technician.registrations.store'), $this->validPayload($area, [
                'name' => 'Submitted Customer',
                'processed_ktp_image' => $this->processedKtpDataUrl(),
            ]))
            ->assertSessionHasNoErrors();

        $this
            ->actingAs($technician)
            ->withSession(['_token' => 'test-token'])
            ->post(route('technician.registrations.store'), $this->validPayload($area, [
                'action' => 'draft',
                'name' => 'Draft Customer',
            ]))
            ->assertSessionHasNoErrors();

        $this
            ->actingAs($technician)
            ->get(route('technician.registrations.index', ['status' => Registration::STATUS_DRAFT]))
            ->assertOk()
            ->assertSee('Draft Customer')
            ->assertDontSee('Submitted Customer');
    }

    private function fakeOcrService(array $parsed = ['source' => 'test']): OcrService
    {
        $ocr = new class($parsed) implements OcrService
        {
            /**
             * @param  array<string, mixed>  $parsed
             */
            public function __construct(private readonly array $parsed) {}

            /** @var array<int, string> */
            public array $paths = [];

            public function readKtp(string $processedImagePath): array
            {
                $this->paths[] = $processedImagePath;

                return [
                    'raw_text' => 'Synthetic OCR result',
                    'parsed' => $this->parsed,
                ];
            }
        };

        $this->app->instance(OcrService::class, $ocr);

        return $ocr;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createArea(array $overrides = []): Area
    {
        return Area::create(array_merge([
            'code' => 'MLG-01',
            'name' => 'Malang Test Area',
            'active' => true,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(Area $area, array $overrides = []): array
    {
        return array_merge([
            '_token' => 'test-token',
            'action' => 'submit',
            'name' => 'Test Customer',
            'nik' => '3500000000000000',
            'phone' => '081234567890',
            'package' => '10MB',
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
            'location_photo' => UploadedFile::fake()->image('location.jpg', 1280, 720),
        ], $overrides);
    }

    private function processedKtpDataUrl(): string
    {
        $file = UploadedFile::fake()->image('processed-ktp.jpg', 1280, 810);

        return 'data:image/jpeg;base64,'.base64_encode(file_get_contents($file->getRealPath()));
    }
}
