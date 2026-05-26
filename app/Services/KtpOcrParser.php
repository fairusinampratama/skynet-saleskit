<?php

namespace App\Services;

class KtpOcrParser
{
    /**
     * @return array<string, mixed>
     */
    public function parse(string $text): array
    {
        $normalized = $this->normalize($text);

        return array_filter([
            'nik' => $this->extractNik($normalized),
            'name' => $this->extractLineValue($normalized, ['NAMA']),
            'birth_place_date' => $this->extractLineValue($normalized, ['TEMPAT/TGL LAHIR', 'TEMPAT TGL LAHIR']),
            'gender' => $this->extractLineValue($normalized, ['JENIS KELAMIN']),
            'address' => $this->extractLineValue($normalized, ['ALAMAT']),
            'rt' => $this->extractRtRw($normalized)['rt'] ?? null,
            'rw' => $this->extractRtRw($normalized)['rw'] ?? null,
            'village' => $this->extractLineValue($normalized, ['KEL/DESA', 'KEL DESA']),
            'district' => $this->extractLineValue($normalized, ['KECAMATAN']),
            'religion' => $this->extractLineValue($normalized, ['AGAMA']),
            'marital_status' => $this->extractLineValue($normalized, ['STATUS PERKAWINAN']),
            'occupation' => $this->extractLineValue($normalized, ['PEKERJAAN']),
            'nationality' => $this->extractLineValue($normalized, ['KEWARGANEGARAAN']),
        ], fn ($value): bool => filled($value));
    }

    private function normalize(string $text): string
    {
        $text = strtoupper($text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/[|]+/', ':', $text) ?? $text;

        return trim($text);
    }

    private function extractNik(string $text): ?string
    {
        if (preg_match('/NIK\s*[:\-]?\s*([0-9OILSB]{12,20})/', $text, $matches)) {
            return $this->cleanDigits($matches[1]);
        }

        if (preg_match('/\b([0-9OILSB]{16})\b/', $text, $matches)) {
            return $this->cleanDigits($matches[1]);
        }

        return null;
    }

    /**
     * @param  array<int, string>  $labels
     */
    private function extractLineValue(string $text, array $labels): ?string
    {
        foreach ($labels as $label) {
            $quoted = preg_quote($label, '/');

            if (preg_match('/^.*'.$quoted.'\s*[:\-]?\s*(.+)$/mi', $text, $matches)) {
                return $this->cleanValue($matches[1]);
            }
        }

        return null;
    }

    /**
     * @return array{rt?: string, rw?: string}
     */
    private function extractRtRw(string $text): array
    {
        if (! preg_match('/RT\s*\/?\s*RW\s*[:\-]?\s*([0-9OILSB]{1,3})\s*\/\s*([0-9OILSB]{1,3})/', $text, $matches)) {
            return [];
        }

        return [
            'rt' => str_pad($this->cleanDigits($matches[1]), 3, '0', STR_PAD_LEFT),
            'rw' => str_pad($this->cleanDigits($matches[2]), 3, '0', STR_PAD_LEFT),
        ];
    }

    private function cleanDigits(string $value): string
    {
        return strtr($value, [
            'O' => '0',
            'I' => '1',
            'L' => '1',
            'S' => '5',
            'B' => '8',
        ]);
    }

    private function cleanValue(string $value): string
    {
        $value = preg_replace('/\s{2,}.*/', '', trim($value)) ?? trim($value);
        $value = preg_replace('/\s+GOL\.?\s*DARAH\s*:?.*$/', '', $value) ?? $value;

        return trim($value, " :\t\n\r\0\x0B");
    }
}
