<?php

namespace Tests\Feature;

use App\Contracts\OcrService;
use App\Models\Area;
use App\Models\Registration;
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

        $response->assertRedirect(route('technician.registrations.show', $registration));

        $this->assertSame(Registration::STATUS_SUBMITTED, $registration->status);
        $this->assertSame($technician->id, $registration->registered_by);
        $this->assertSame('Test Customer', $registration->name);
        $this->assertSame('3500000000000000', $registration->nik);
        $this->assertStringStartsWith('ktp/', $registration->ktp_photo_path);
        $this->assertStringStartsWith('registration-location/', $registration->location_photo_path);
        $this->assertSame([], $ocr->paths);

        Storage::disk('public')->assertExists($registration->ktp_photo_path);
        Storage::disk('public')->assertExists($registration->location_photo_path);
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

        $this->assertStringStartsWith('ktp/', $registration->ktp_photo_path);
        $this->assertSame([], $ocr->paths);
        Storage::disk('public')->assertExists($registration->ktp_photo_path);
    }

    public function test_technician_can_submit_registration_without_location_photo(): void
    {
        Storage::fake('public');

        $this->fakeOcrService();
        $technician = User::factory()->create(['role' => 'technician']);
        $area = $this->createArea();
        $payload = $this->validPayload($area, [
            'processed_ktp_image' => $this->processedKtpDataUrl(),
        ]);
        unset($payload['location_photo']);

        $response = $this
            ->actingAs($technician)
            ->withSession(['_token' => 'test-token'])
            ->post(route('technician.registrations.store'), $payload);

        $response->assertSessionHasNoErrors();

        $registration = Registration::query()->sole();

        $this->assertSame(Registration::STATUS_SUBMITTED, $registration->status);
        $this->assertNull($registration->location_photo_path);
    }

    public function test_technician_can_submit_registration_with_processed_location_photo(): void
    {
        Storage::fake('public');

        $this->fakeOcrService();
        $technician = User::factory()->create(['role' => 'technician']);
        $area = $this->createArea();
        $payload = $this->validPayload($area, [
            'processed_ktp_image' => $this->processedKtpDataUrl(),
            'processed_location_photo' => $this->processedLocationPhotoDataUrl(),
        ]);
        unset($payload['location_photo']);

        $response = $this
            ->actingAs($technician)
            ->withSession(['_token' => 'test-token'])
            ->post(route('technician.registrations.store'), $payload);

        $response->assertSessionHasNoErrors();

        $registration = Registration::query()->sole();

        $this->assertStringStartsWith('registration-location/', $registration->location_photo_path);
        Storage::disk('public')->assertExists($registration->location_photo_path);
    }

    public function test_registration_rejects_invalid_processed_location_photo(): void
    {
        Storage::fake('public');

        $this->fakeOcrService();
        $technician = User::factory()->create(['role' => 'technician']);
        $area = $this->createArea();
        $payload = $this->validPayload($area, [
            'processed_ktp_image' => $this->processedKtpDataUrl(),
            'processed_location_photo' => 'not-a-data-url',
        ]);
        unset($payload['location_photo']);

        $response = $this
            ->actingAs($technician)
            ->withSession(['_token' => 'test-token'])
            ->post(route('technician.registrations.store'), $payload);

        $response->assertSessionHasErrors(['processed_location_photo']);

        $this->assertSame(0, Registration::query()->count());
    }

    public function test_registration_readiness_does_not_require_location_photo(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        $area = $this->createArea();

        $registration = Registration::create([
            'area_id' => $area->id,
            'registered_by' => $technician->id,
            'name' => 'Ready Customer',
            'nik' => '3500000000000012',
            'phone' => '081234567892',
            'package' => '50MB',
            'installation_full_address' => 'Install Address',
            'latitude' => '-7.96662000',
            'longitude' => '112.63263000',
            'ktp_photo_path' => 'ktp/test.jpg',
            'status' => Registration::STATUS_DRAFT,
        ]);

        $readiness = $registration->technicianReadiness();

        $this->assertTrue($readiness['complete']);
        $this->assertFalse($readiness['has_evidence']);
        $this->assertNotContains('Foto lokasi', $readiness['missing']);
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

        $this->assertStringStartsWith('ktp/', $registration->ktp_photo_path);
        $this->assertSame([], $ocr->paths);
        Storage::disk('public')->assertExists($registration->ktp_photo_path);
    }

    public function test_registration_submit_requires_core_fields_and_ktp_photo(): void
    {
        Storage::fake('public');

        $this->fakeOcrService();
        $technician = User::factory()->create(['role' => 'technician']);

        $response = $this
            ->actingAs($technician)
            ->withSession(['_token' => 'test-token'])
            ->post(route('technician.registrations.store'), [
                '_token' => 'test-token',
                'action' => 'submit',
            ]);

        $response->assertSessionHasErrors([
            'name',
            'nik',
            'phone',
            'package',
            'area_id',
            'installation_full_address',
            'latitude',
            'longitude',
            'ktp_image',
        ]);

        $this->assertSame(0, Registration::query()->count());
    }

    public function test_registration_submit_rejects_invalid_identity_contact_area_and_coordinates(): void
    {
        Storage::fake('public');

        $this->fakeOcrService();
        $technician = User::factory()->create(['role' => 'technician']);
        $inactiveArea = $this->createArea(['active' => false]);

        $response = $this
            ->actingAs($technician)
            ->withSession(['_token' => 'test-token'])
            ->post(route('technician.registrations.store'), $this->validPayload($inactiveArea, [
                'nik' => 'not-a-valid-nik',
                'phone' => 'abc',
                'package' => '999MB',
                'latitude' => '100',
                'longitude' => '200',
                'processed_ktp_image' => $this->processedKtpDataUrl(),
            ]));

        $response->assertSessionHasErrors([
            'nik',
            'phone',
            'package',
            'area_id',
            'latitude',
            'longitude',
        ]);

        $this->assertSame(0, Registration::query()->count());
    }

    public function test_technician_can_submit_existing_draft_without_reuploading_ktp(): void
    {
        Storage::fake('public');

        $this->fakeOcrService();
        $technician = User::factory()->create(['role' => 'technician']);
        $area = $this->createArea();

        $this
            ->actingAs($technician)
            ->withSession(['_token' => 'test-token'])
            ->post(route('technician.registrations.store'), $this->validPayload($area, [
                'action' => 'draft',
                'processed_ktp_image' => $this->processedKtpDataUrl(),
            ]))
            ->assertSessionHasNoErrors();

        $registration = Registration::query()->sole();

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
        $this->assertStringStartsWith('ktp/', $registration->ktp_photo_path);
    }

    public function test_technician_cannot_edit_submitted_registration(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        $registration = Registration::create([
            'registered_by' => $technician->id,
            'status' => Registration::STATUS_SUBMITTED,
            'name' => 'Submitted Customer',
            'phone' => '081234567890',
        ]);

        $this
            ->actingAs($technician)
            ->get(route('technician.registrations.edit', $registration))
            ->assertForbidden();
    }

    public function test_edit_form_shows_existing_ktp_photo_state(): void
    {
        Storage::fake('public');

        $technician = User::factory()->create(['role' => 'technician']);
        Storage::disk('public')->put('ktp/existing.jpg', 'ktp');
        $registration = Registration::create([
            'registered_by' => $technician->id,
            'status' => Registration::STATUS_DRAFT,
            'name' => 'Existing KTP Customer',
            'phone' => '081234567890',
            'ktp_photo_path' => 'ktp/existing.jpg',
        ]);

        $this
            ->actingAs($technician)
            ->get(route('technician.registrations.edit', $registration))
            ->assertOk()
            ->assertSee('data-existing-ktp-document="1"', false)
            ->assertSee('/storage/ktp/existing.jpg', false);
    }

    public function test_technician_can_scan_ktp_for_ocr_autofill_data(): void
    {
        Storage::fake('public');

        $ocr = $this->fakeOcrService([
            'nik' => '3500000000000002',
            'name' => 'OCR CUSTOMER',
            'address' => 'OCR KTP ADDRESS',
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
        $this->assertSame(0, Registration::query()->count());
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

    public function test_technician_can_submit_registration_without_ktp_address(): void
    {
        Storage::fake('public');

        $this->fakeOcrService();
        $technician = User::factory()->create(['role' => 'technician']);
        $area = $this->createArea();
        $payload = $this->validPayload($area, [
            'package' => '25MB',
            'ktp_full_address' => null,
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
            'ktp_photo_path' => 'ktp/test.jpg',
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
            'ktp_photo_url' => 'ktp/test.jpg',
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
            ->assertDontSee('Submitted Customer')
            ->assertDontSee('Perlu Revisi')
            ->assertDontSee('Dibatalkan');
    }

    public function test_deleting_registration_removes_photos(): void
    {
        Storage::fake('public');

        $technician = User::factory()->create(['role' => 'technician']);
        $area = $this->createArea();
        Storage::disk('public')->put('ktp/delete-me.jpg', 'ktp');
        Storage::disk('public')->put('registration-location/delete-me.jpg', 'location');

        $registration = Registration::create([
            'area_id' => $area->id,
            'registered_by' => $technician->id,
            'name' => 'Delete Me',
            'phone' => '081234567890',
            'ktp_photo_path' => 'ktp/delete-me.jpg',
            'location_photo_path' => 'registration-location/delete-me.jpg',
        ]);

        $registration->delete();

        Storage::disk('public')->assertMissing('ktp/delete-me.jpg');
        Storage::disk('public')->assertMissing('registration-location/delete-me.jpg');
    }

    /**
     * @return OcrService&object{paths: array<int, string>}
     */
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

    private function processedLocationPhotoDataUrl(): string
    {
        $file = UploadedFile::fake()->image('processed-location.jpg', 1280, 720);

        return 'data:image/jpeg;base64,'.base64_encode(file_get_contents($file->getRealPath()));
    }
}
