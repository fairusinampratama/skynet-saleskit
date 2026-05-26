<?php

namespace App\Services;

use App\Contracts\OcrService;

class PlaceholderOcrService implements OcrService
{
    public function readKtp(string $processedImagePath): array
    {
        return [
            'raw_text' => null,
            'parsed' => [],
        ];
    }
}
