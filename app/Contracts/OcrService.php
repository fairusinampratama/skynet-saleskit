<?php

namespace App\Contracts;

interface OcrService
{
    /**
     * @return array{raw_text: string|null, parsed: array<string, mixed>, confidence?: array<string, mixed>, warnings?: array<int, string>, raw_text_path?: string|null, variants?: array<int, array<string, mixed>>}
     */
    public function readKtp(string $processedImagePath): array;
}
