<?php

namespace App\Services\Ocr;

use Illuminate\Support\Facades\Http;

class PaddleOcrHttpClient
{
    /**
     * @return array{text: string, items: array<int, array<string, mixed>>}
     */
    public function read(string $path): array
    {
        $baseUrl = rtrim((string) config('services.paddleocr.url'), '/');
        $endpoint = '/'.ltrim((string) config('services.paddleocr.endpoint', '/ktp/read'), '/');
        $fileField = (string) config('services.paddleocr.file_field', 'image');

        if ($baseUrl === '') {
            throw new \RuntimeException('PaddleOCR service URL is not configured.');
        }

        if ($fileField === '') {
            throw new \RuntimeException('PaddleOCR file field is not configured.');
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new \RuntimeException('KTP image could not be read.');
        }

        $request = Http::timeout((int) config('services.paddleocr.timeout'))
            ->attach($fileField, $contents, basename($path));

        $apiKey = (string) config('services.paddleocr.api_key');

        if ($apiKey !== '') {
            $request = $request->withHeader('X-API-Key', $apiKey);
        }

        $response = $request->post($baseUrl.$endpoint);

        if (! $response->successful()) {
            throw new \RuntimeException($response->json('detail') ?: 'PaddleOCR failed.');
        }

        $decoded = $response->json();

        if (! is_array($decoded)) {
            throw new \RuntimeException('PaddleOCR returned invalid JSON.');
        }

        return $this->normalizeResponse($decoded);
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array{text: string, items: array<int, array<string, mixed>>}
     */
    private function normalizeResponse(array $decoded): array
    {
        if (isset($decoded['text']) && is_string($decoded['text'])) {
            return [
                'text' => trim($decoded['text']),
                'items' => is_array($decoded['items'] ?? null) ? $decoded['items'] : [],
            ];
        }

        if (is_array($decoded['results'] ?? null)) {
            $items = array_values(array_filter(
                $decoded['results'],
                fn (mixed $item): bool => is_array($item),
            ));
            $text = collect($items)
                ->pluck('text')
                ->filter(fn (mixed $text): bool => is_string($text) && trim($text) !== '')
                ->map(fn (string $text): string => trim($text))
                ->implode("\n");

            return [
                'text' => $text,
                'items' => $items,
            ];
        }

        throw new \RuntimeException('PaddleOCR returned invalid JSON.');
    }
}
