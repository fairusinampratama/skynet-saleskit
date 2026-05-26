<?php

namespace App\Contracts;

interface OcrService
{
    /**
     * @return array{raw_text: string|null, parsed: array<string, mixed>}
     */
    public function readKtp(string $processedImagePath): array;
}
