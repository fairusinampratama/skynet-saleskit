<?php

namespace Tests\Unit;

use App\Services\PaddleOcrService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaddleOcrServiceTest extends TestCase
{
    public function test_it_reads_ktp_text_from_paddleocr_service(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('ktp/processed/sample.jpg', 'fake-image-bytes');
        config(['services.paddleocr.url' => 'http://ocr:8080']);

        Http::fake([
            'http://ocr:8080/ktp/read' => Http::response([
                'engine' => 'paddleocr',
                'text' => "NIK : 3507070606000001\nNama : RAFIT CAHYADI\nAlamat : DUKUH ARAN-ARAN",
                'items' => [],
            ]),
        ]);

        $result = app(PaddleOcrService::class)->readKtp('ktp/processed/sample.jpg');

        $this->assertSame('3507070606000001', $result['parsed']['nik']);
        $this->assertSame('RAFIT CAHYADI', $result['parsed']['name']);
        $this->assertSame('paddleocr', $result['variants'][0]['variant']);

        Http::assertSent(fn ($request): bool => $request->url() === 'http://ocr:8080/ktp/read');
    }

    public function test_it_reads_ktp_text_from_external_ocr_results_response(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('ktp/processed/sample.jpg', 'fake-image-bytes');
        config([
            'services.paddleocr.url' => 'http://149.28.179.28:8082',
            'services.paddleocr.endpoint' => '/ocr',
            'services.paddleocr.file_field' => 'file',
            'services.paddleocr.api_key' => 'secret-key',
        ]);

        Http::fake([
            'http://149.28.179.28:8082/ocr' => Http::response([
                'results' => [
                    ['text' => 'NIK : 3507070606000001', 'confidence' => 0.9967, 'box' => []],
                    ['text' => 'Nama : RAFIT CAHYADI', 'confidence' => 0.98, 'box' => []],
                    ['text' => 'Alamat : DUKUH ARAN-ARAN', 'confidence' => 0.95, 'box' => []],
                ],
                'count' => 3,
            ]),
        ]);

        $result = app(PaddleOcrService::class)->readKtp('ktp/processed/sample.jpg');

        $this->assertSame("NIK : 3507070606000001\nNama : RAFIT CAHYADI\nAlamat : DUKUH ARAN-ARAN", $result['raw_text']);
        $this->assertSame('3507070606000001', $result['parsed']['nik']);
        $this->assertSame('RAFIT CAHYADI', $result['parsed']['name']);

        Http::assertSent(fn ($request): bool => $request->url() === 'http://149.28.179.28:8082/ocr'
            && $request->hasFile('file', 'fake-image-bytes', 'sample.jpg')
            && $request->hasHeader('X-API-Key', 'secret-key'));
    }

    public function test_it_returns_manual_fallback_when_paddleocr_service_fails(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('ktp/processed/sample.jpg', 'fake-image-bytes');
        config(['services.paddleocr.url' => 'http://ocr:8080']);

        Http::fake([
            'http://ocr:8080/ktp/read' => Http::response(['detail' => 'OCR service unavailable'], 503),
        ]);

        $result = app(PaddleOcrService::class)->readKtp('ktp/processed/sample.jpg');

        $this->assertNull($result['raw_text']);
        $this->assertSame('OCR belum tersedia. Isi data KTP secara manual.', $result['parsed']['ocr_error']);
        $this->assertSame(['ocr_failed'], $result['warnings']);
        $this->assertSame([], $result['variants']);
    }
}
