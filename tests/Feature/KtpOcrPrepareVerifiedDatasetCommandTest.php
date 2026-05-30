<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class KtpOcrPrepareVerifiedDatasetCommandTest extends TestCase
{
    public function test_it_stages_original_images_with_expected_and_metadata_templates(): void
    {
        $source = storage_path('framework/testing/ocr-source');
        $output = storage_path('framework/testing/ocr-verified');

        File::deleteDirectory($source);
        File::deleteDirectory($output);
        File::ensureDirectoryExists($source.'/customer-001');
        File::ensureDirectoryExists($source.'/customer-002');

        File::put($source.'/customer-001/original.jpg', 'fake image one');
        File::put($source.'/customer-001/normalized.jpg', 'ignored variant');
        File::put($source.'/customer-002/original.png', 'fake image two');

        $this
            ->artisan('ktp:ocr-prepare-verified-dataset', [
                '--source' => $source,
                '--output' => $output,
                '--limit' => 1,
            ])
            ->assertSuccessful();

        $this->assertFileExists($output.'/case-001/original.jpg');
        $this->assertFileExists($output.'/case-001/expected.json');
        $this->assertFileExists($output.'/case-001/metadata.json');
        $this->assertFileExists($output.'/audit.csv');
        $this->assertFileDoesNotExist($output.'/case-002/original.png');

        $expected = json_decode((string) file_get_contents($output.'/case-001/expected.json'), true);
        $metadata = json_decode((string) file_get_contents($output.'/case-001/metadata.json'), true);

        $this->assertSame('', $expected['nik']);
        $this->assertSame('needs_review', $metadata['audit_status']);
        $this->assertFalse($metadata['scored']);
        $this->assertContains('manual_expected_required', $metadata['audit_notes']);
        $this->assertStringContainsString('case-001', (string) file_get_contents($output.'/audit.csv'));

        File::deleteDirectory($source);
        File::deleteDirectory($output);
    }
}
