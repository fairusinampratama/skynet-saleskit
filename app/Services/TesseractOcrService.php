<?php

namespace App\Services;

use App\Contracts\OcrService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

class TesseractOcrService implements OcrService
{
    public function __construct(private readonly KtpOcrParser $parser) {}

    public function readKtp(string $processedImagePath): array
    {
        $path = Storage::disk('public')->path($processedImagePath);

        if (! is_file($path)) {
            return [
                'raw_text' => null,
                'parsed' => ['ocr_error' => 'Processed KTP image was not found.'],
            ];
        }

        try {
            $rawText = $this->runTesseract($path, 'ind+eng');
        } catch (Throwable $exception) {
            try {
                $rawText = $this->runTesseract($path, 'eng');
            } catch (Throwable $fallbackException) {
                return [
                    'raw_text' => null,
                    'parsed' => ['ocr_error' => $fallbackException->getMessage()],
                ];
            }
        }

        return [
            'raw_text' => $rawText,
            'parsed' => $this->parser->parse($rawText),
        ];
    }

    private function runTesseract(string $path, string $language): string
    {
        $process = new Process([
            'tesseract',
            $path,
            'stdout',
            '-l',
            $language,
            '--psm',
            '6',
        ]);
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException(trim($process->getErrorOutput()) ?: 'Tesseract OCR failed.');
        }

        return trim($process->getOutput());
    }
}
