<?php

namespace App\Services;

use App\Contracts\OcrService;
use App\Services\Ocr\KtpOcrConfidenceScorer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Throwable;

class EasyOcrService implements OcrService
{
    public function __construct(
        private readonly KtpOcrParser $parser,
        private readonly KtpOcrConfidenceScorer $confidenceScorer,
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
            $ocr = $this->runEasyOcr($path);
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
            'easyocr' => [
                'variant' => 'easyocr',
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
                    'variant' => 'easyocr',
                    'score' => $variantResults['easyocr']['score'],
                    'warnings' => $parserWarnings,
                ],
            ],
        ];
    }

    /**
     * @return array{text: string, languages: array<int, string>}
     */
    private function runEasyOcr(string $path): array
    {
        $python = env('EASYOCR_PYTHON') ?: 'python3';
        $script = env('EASYOCR_SCRIPT') ?: base_path('scripts/ocr/easyocr_ktp.py');
        $process = new Process([$python, $script, $path]);
        $libraryPath = $this->ocrLibraryPath();

        if ($libraryPath !== null) {
            $process->setEnv(['LD_LIBRARY_PATH' => $libraryPath]);
        }

        $process->setTimeout(max(30, (int) (env('EASYOCR_TIMEOUT') ?: 180)));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException(trim($process->getErrorOutput()) ?: 'EasyOCR failed.');
        }

        $decoded = json_decode($process->getOutput(), true);

        if (! is_array($decoded) || ! isset($decoded['text']) || ! is_string($decoded['text'])) {
            throw new \RuntimeException('EasyOCR returned invalid JSON.');
        }

        return [
            'text' => trim($decoded['text']),
            'languages' => array_values(array_filter($decoded['languages'] ?? [], 'is_string')),
        ];
    }

    private function ocrLibraryPath(): ?string
    {
        $paths = array_filter([
            $this->firstMatchingDirectory('/nix/store/*-gcc-*-lib/lib'),
            $this->firstMatchingDirectory('/nix/store/*-zlib-*/lib'),
            $this->firstMatchingDirectory('/nix/store/*-glib-*/lib'),
            $this->firstMatchingDirectory('/nix/store/*-libglvnd-*/lib'),
            $this->firstMatchingDirectory('/nix/store/*-mesa-*/lib'),
        ]);

        $existing = array_filter(explode(':', getenv('LD_LIBRARY_PATH') ?: ''));
        $paths = array_values(array_unique(array_merge($paths, $existing)));

        return $paths === [] ? null : implode(':', $paths);
    }

    private function firstMatchingDirectory(string $pattern): ?string
    {
        foreach (glob($pattern) ?: [] as $path) {
            if (is_dir($path)) {
                return $path;
            }
        }

        return null;
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
            'rt' => 1,
            'rw' => 1,
            'village' => 2,
            'district' => 2,
            'province' => 1,
            'city' => 1,
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
