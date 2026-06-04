<?php

namespace App\Services;

use App\Contracts\OcrService;
use App\Services\Ocr\KtpOcrConfidenceScorer;
use App\Services\Ocr\PaddleOcrHttpClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PaddleOcrService implements OcrService
{
    public function __construct(
        private readonly KtpOcrParser $parser,
        private readonly KtpOcrConfidenceScorer $confidenceScorer,
        private readonly PaddleOcrHttpClient $ocrClient,
    ) {}

    public function readKtp(string $processedImagePath): array
    {
        $path = Storage::disk('public')->path($processedImagePath);

        if (! is_file($path)) {
            return [
                'raw_text' => null,
                'parsed' => ['ocr_error' => 'Foto KTP belum tersedia. Ambil atau unggah foto KTP terlebih dulu.'],
            ];
        }

        try {
            $ocr = $this->runPaddleOcr($path);
        } catch (Throwable $exception) {
            Log::warning('KTP OCR failed.', [
                'processed_image_path' => $processedImagePath,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return [
                'raw_text' => null,
                'parsed' => ['ocr_error' => 'OCR belum tersedia. Isi data KTP secara manual.'],
                'confidence' => [
                    'fields' => [],
                    'overall' => 'low',
                    'status' => 'manual_entry_required',
                    'warnings' => ['ocr_failed'],
                ],
                'warnings' => ['ocr_failed'],
                'raw_text_path' => null,
                'variants' => [],
            ];
        }

        $parsed = $this->parser->parse($ocr['text']);
        $parserWarnings = $parsed['_warnings'] ?? [];
        unset($parsed['_warnings']);

        $variantResults = [
            'paddleocr' => [
                'variant' => 'paddleocr',
                'raw_text' => $ocr['text'],
                'parsed' => $parsed,
                'warnings' => $parserWarnings,
                'score' => $this->score($parsed, $parserWarnings),
            ],
        ];
        $confidence = $this->confidenceScorer->score($parsed, $variantResults);
        $warnings = array_values(array_unique(array_merge($parserWarnings, $confidence['warnings'])));

        return [
            'raw_text' => $ocr['text'],
            'parsed' => $parsed,
            'confidence' => $confidence,
            'warnings' => $warnings,
            'raw_text_path' => null,
            'variants' => [
                [
                    'variant' => 'paddleocr',
                    'score' => $variantResults['paddleocr']['score'],
                    'warnings' => $parserWarnings,
                ],
            ],
        ];
    }

    /**
     * @return array{text: string, items: array<int, array<string, mixed>>}
     */
    private function runPaddleOcr(string $path): array
    {
        return $this->ocrClient->read($path);
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<int, string>  $warnings
     */
    private function score(array $parsed, array $warnings): int
    {
        $weights = [
            'nik' => 5,
            'name' => 3,
            'address' => 3,
        ];
        $score = 0;

        foreach ($weights as $field => $weight) {
            if (filled($parsed[$field] ?? null)) {
                $score += $weight;
            }
        }

        return max(0, $score - count($warnings));
    }
}
