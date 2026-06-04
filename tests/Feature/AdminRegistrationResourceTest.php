<?php

namespace Tests\Feature;

use App\Filament\Resources\Registrations\RegistrationResource;
use App\Filament\Resources\Registrations\Schemas\RegistrationForm;
use App\Models\Registration;
use App\Models\User;
use Filament\Forms\Components\FileUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class AdminRegistrationResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_resource_exposes_crud_pages(): void
    {
        $this->assertSame([
            'index',
            'create',
            'view',
            'edit',
        ], array_keys(RegistrationResource::getPages()));
    }

    public function test_admin_registration_photo_uploads_are_previewable_and_openable(): void
    {
        $method = new ReflectionMethod(RegistrationForm::class, 'photoUpload');
        $method->setAccessible(true);

        /** @var FileUpload $upload */
        $upload = $method->invoke(null, 'ktp_photo_path', 'Foto KTP', 'ktp');

        $this->assertSame('public', $upload->getDiskName());
        $this->assertSame('public', $upload->getVisibility());
        $this->assertSame('ktp', $upload->getDirectory());
        $this->assertSame('260', $upload->getImagePreviewHeight());
        $this->assertSame(1.58, $upload->getItemPanelAspectRatio());
        $this->assertTrue($upload->isPreviewable());
        $this->assertTrue($upload->isOpenable());
        $this->assertTrue($upload->isDownloadable());
    }

    public function test_admin_accept_semantics_set_review_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $technician = User::factory()->create(['role' => 'technician']);
        $registration = Registration::create([
            'registered_by' => $technician->id,
            'status' => Registration::STATUS_SUBMITTED,
            'name' => 'Approve Me',
            'nik' => '3500000000000098',
            'phone' => '081234567898',
            'package' => '50MB',
            'installation_full_address' => 'Install Address',
            'latitude' => '-7.96662000',
            'longitude' => '112.63263000',
            'ktp_photo_path' => 'ktp/approve-me.jpg',
        ]);

        $registration->update([
            'status' => Registration::STATUS_APPROVED,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        $registration->refresh();

        $this->assertSame(Registration::STATUS_APPROVED, $registration->status);
        $this->assertSame($admin->id, $registration->reviewed_by);
        $this->assertNotNull($registration->reviewed_at);
    }
}
