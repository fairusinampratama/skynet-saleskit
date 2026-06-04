<?php

namespace Tests\Unit;

use App\Services\Ocr\KtpOcrBenchmarkService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KtpOcrBenchmarkServiceTest extends TestCase
{
    public function test_it_benchmarks_paddleocr_and_records_duration(): void
    {
        $directory = sys_get_temp_dir().'/saleskit-ocr-benchmark-'.uniqid();
        File::ensureDirectoryExists($directory);
        file_put_contents($directory.'/ktp.jpg', 'fake-image-bytes');
        config(['services.paddleocr.url' => 'http://ocr:8080']);

        Http::fake([
            'http://ocr:8080/ktp/read' => Http::response([
                'engine' => 'paddleocr',
                'text' => "NIK : 3507070606000001\nNama : RAFIT CAHYADI\nAlamat : DUKUH ARAN-ARAN",
                'items' => [],
            ]),
        ]);

        $result = app(KtpOcrBenchmarkService::class)->run([
            'source' => 'directory',
            'input' => $directory,
            'limit' => 1,
            'engine' => 'paddleocr',
            'variant' => 'original',
        ]);

        $this->assertSame(1, $result['case_count']);
        $this->assertSame(1, $result['summary']['field_hits']['nik']);
        $this->assertSame(1, $result['summary']['duration_ms']['count']);
        $this->assertIsInt($result['summary']['duration_ms']['average']);

        File::deleteDirectory($directory);
    }

    public function test_it_benchmarks_external_paddleocr_results_response(): void
    {
        $directory = sys_get_temp_dir().'/saleskit-ocr-benchmark-'.uniqid();
        File::ensureDirectoryExists($directory);
        file_put_contents($directory.'/ktp.jpg', 'fake-image-bytes');
        config([
            'services.paddleocr.url' => 'http://149.28.179.28:8082',
            'services.paddleocr.endpoint' => '/ocr',
            'services.paddleocr.file_field' => 'file',
        ]);

        Http::fake([
            'http://149.28.179.28:8082/ocr' => Http::response([
                'results' => [
                    ['text' => 'NIK : 3507070606000001', 'confidence' => 0.9967, 'box' => []],
                    ['text' => 'Nama : RAFIT CAHYADI', 'confidence' => 0.98, 'box' => []],
                ],
                'count' => 2,
            ]),
        ]);

        $result = app(KtpOcrBenchmarkService::class)->run([
            'source' => 'directory',
            'input' => $directory,
            'limit' => 1,
            'engine' => 'paddleocr',
            'variant' => 'original',
        ]);

        $this->assertSame(1, $result['case_count']);
        $this->assertSame(1, $result['summary']['field_hits']['nik']);

        Http::assertSent(fn ($request): bool => $request->url() === 'http://149.28.179.28:8082/ocr'
            && $request->hasFile('file', 'fake-image-bytes', 'ktp.jpg'));

        File::deleteDirectory($directory);
    }
}
