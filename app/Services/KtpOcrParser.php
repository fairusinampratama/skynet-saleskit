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

        $parsed = array_filter([
            'nik' => $this->extractNik($normalized),
            'name' => $this->extractFieldValue($normalized, ['NAMA LENGKAP', 'NAMA', 'N4MA', 'N4M4'], 'name', 'previous') ?: $this->extractNameFallback($normalized),
            'birth_place_date' => $this->extractLineValue($normalized, ['TEMPAT/TGL LAHIR', 'TEMPAT TGL LAHIR', 'TEMPAT/TGI LAHIR', 'TEMPAT TGI LAHIR', 'TANGGAL LAHIR', 'TEMPAT LAHIR', 'TTL']),
            'gender' => $this->extractLineValue($normalized, ['JENIS KELAMIN', 'JENIS KELAM1N']),
            'address' => $this->extractFieldValue($normalized, ['ALAMAT SEKARANG', 'ALAMAT', 'ALAMAI'], 'address', 'next') ?: $this->extractAddressFallback($normalized),
            'religion' => $this->extractLineValue($normalized, ['AGAMA']),
            'marital_status' => $this->extractLineValue($normalized, ['STATUS PERKAWINAN', 'STATUS PERKAW1NAN']),
            'occupation' => $this->extractLineValue($normalized, ['PEKERJAAN', 'PEKERJA4N']),
            'nationality' => $this->extractLineValue($normalized, ['KEWARGANEGARAAN', 'KEWARGANEGARA4N']),
        ], fn ($value): bool => filled($value));

        $warnings = $this->warnings($parsed, $normalized);

        if ($warnings !== []) {
            $parsed['_warnings'] = $warnings;
        }

        return $parsed;
    }

    private function normalize(string $text): string
    {
        $text = strtoupper($text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = strtr($text, [
            '“' => '"',
            '”' => '"',
            '‘' => "'",
            '’' => "'",
        ]);
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/[|]+/', ':', $text) ?? $text;
        $text = preg_replace('/\bRTRW\b/', 'RT/RW', $text) ?? $text;
        $text = preg_replace('/\bRTIRW\b/', 'RT/RW', $text) ?? $text;
        $text = preg_replace('/\bRIRW\b/', 'RT/RW', $text) ?? $text;
        $text = preg_replace('/\bRT\s+RW\b/', 'RT/RW', $text) ?? $text;
        $text = preg_replace('/\bKEL\s*\/?\s*DESA\b/', 'KEL/DESA', $text) ?? $text;
        $text = preg_replace('/\bKEV\s*\/?\s*DESA\b/', 'KEL/DESA', $text) ?? $text;
        $text = preg_replace('/\bKABUPATEN(?=[A-Z])/', 'KABUPATEN ', $text) ?? $text;
        $text = preg_replace('/\bKOTA(?=[A-Z])/', 'KOTA ', $text) ?? $text;
        $text = preg_replace('/\bTEMPAT\s*\/?\s*TGI\s+LAHIR\b/', 'TEMPAT/TGL LAHIR', $text) ?? $text;
        $text = preg_replace('/\bN[1I|!]K\b/', 'NIK', $text) ?? $text;

        return trim($text);
    }

    private function extractNik(string $text): ?string
    {
        if (preg_match('/(?:NIK|YK|YIK)\s*[:\-!|.]?\s*([0-9OILSBZ\s]{12,30})/i', $text, $matches)) {
            $nik = substr($this->cleanDigits($matches[1]), 0, 16);

            return strlen($nik) >= 12 ? $nik : null;
        }

        if (preg_match('/\b([0-9OILSBZ\s]{16,26})\b/', $text, $matches)) {
            $nik = substr($this->cleanDigits($matches[1]), 0, 16);

            return strlen($nik) >= 12 ? $nik : null;
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

            if (preg_match('/^.*\b'.$quoted.'\b[ \t]*[:\-.\']?[ \t]*(.+)$/mi', $text, $matches)) {
                return $this->cleanValue($matches[1]);
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $labels
     */
    private function extractFieldValue(string $text, array $labels, string $type, string $preferredDirection): ?string
    {
        $sameLine = $this->extractLineValue($text, $labels);

        if ($sameLine) {
            return $sameLine;
        }

        $lines = $this->lines($text);

        foreach ($lines as $index => $line) {
            if (! $this->isLabelOnlyLine($line, $labels)) {
                continue;
            }

            $candidates = $preferredDirection === 'next'
                ? [$lines[$index + 1] ?? null, $lines[$index - 1] ?? null]
                : [$lines[$index - 1] ?? null, $lines[$index + 1] ?? null];

            foreach ($candidates as $candidate) {
                if (! is_string($candidate) || ! $this->isPlausibleAdjacentValue($candidate, $type)) {
                    continue;
                }

                return $this->cleanValue($candidate);
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $labels
     */
    private function isLabelOnlyLine(string $line, array $labels): bool
    {
        $line = trim($line, " \t\n\r\0\x0B:.-'");

        foreach ($labels as $label) {
            if (preg_match('/^'.preg_quote($label, '/').'$/i', $line)) {
                return true;
            }
        }

        return false;
    }

    private function isPlausibleAdjacentValue(string $line, string $type): bool
    {
        $line = $this->cleanValue($line);

        if ($line === '' || $this->looksLikeLabelLine($line)) {
            return false;
        }

        return match ($type) {
            'name' => (bool) preg_match('/^[A-Z][A-Z .\'-]{2,}$/', $line) && ! preg_match('/\d/', $line),
            'address' => (bool) preg_match('/[A-Z]/', $line) && ! preg_match('/^(PROVINSI|KABUPATEN|KOTA)\b/', $line),
            default => $line !== '',
        };
    }

    private function extractNameFallback(string $text): ?string
    {
        $lines = $this->lines($text);

        foreach ($lines as $index => $line) {
            if (! str_contains($line, 'NIK')) {
                continue;
            }

            foreach (array_slice($lines, $index + 1, 4) as $candidate) {
                if ($this->looksLikeLabelLine($candidate)) {
                    continue;
                }

                if (preg_match('/^[A-Z][A-Z .\'-]{3,}$/', $candidate) && ! preg_match('/\d/', $candidate)) {
                    return $this->cleanValue($candidate);
                }
            }
        }

        return null;
    }

    private function extractAddressFallback(string $text): ?string
    {
        $lines = $this->lines($text);

        foreach ($lines as $index => $line) {
            if (! preg_match('/^RT\s*\/?\s*RW$/i', trim($line))) {
                continue;
            }

            foreach ([$lines[$index - 1] ?? null, $lines[$index - 2] ?? null] as $candidate) {
                if (! is_string($candidate) || ! $this->isPlausibleAdjacentValue($candidate, 'address')) {
                    continue;
                }

                return $this->cleanValue($candidate);
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function lines(string $text): array
    {
        return array_values(array_filter(
            array_map(fn (string $line): string => trim($line, " \t\n\r\0\x0B:|"), explode("\n", $text)),
            fn (string $line): bool => $line !== '',
        ));
    }

    private function looksLikeLabelLine(string $line): bool
    {
        return (bool) preg_match('/\b(NIK|NAMA|TEMPAT|TGL|LAHIR|JENIS|KELAMIN|ALAMAT|RT\/RW|KEL\/DESA|DESA\/KELURAHAN|KECAMATAN|KABUPATEN|PROVINSI|AGAMA|STATUS|PEKERJAAN|KEWARGANEGARAAN|BERLAKU)\b/', $line);
    }

    private function cleanDigits(string $value): string
    {
        $value = strtr($value, [
            'O' => '0',
            'I' => '1',
            'L' => '1',
            'S' => '5',
            'B' => '8',
            'Z' => '2',
        ]);

        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private function cleanValue(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/^[^\p{L}\p{N}]+/u', '', $value) ?? $value;
        $value = preg_replace('/^(ALAMAT|NAMA|NAMA LENGKAP|DESA\/KELURAHAN|KABUPATEN\/KOTA)\s*[:\-.\']?\s*/u', '', $value) ?? $value;
        $value = preg_replace('/[^\p{L}\p{N}\-\/,. ]+$/u', '', $value) ?? $value;
        $value = preg_replace('/\s{2,}.*/', '', trim($value)) ?? trim($value);
        $value = preg_replace('/\s+[|:=].*$/', '', $value) ?? $value;
        $value = preg_replace('/\s+GOL\.?\s*DARAH\s*:?.*$/', '', $value) ?? $value;
        $value = $this->stripTrailingOcrArtifacts($value);

        return trim($value, " .,:;'-\t\n\r\0\x0B");
    }

    private function stripTrailingOcrArtifacts(string $value): string
    {
        $artifacts = ['B', 'CP', 'DA', 'F', 'I', 'IES', 'J', 'P', 'PIL', 'PM', 'SI', 'UI', 'Y'];
        $parts = preg_split('/\s+/', trim($value)) ?: [];

        while (count($parts) > 1 && in_array(end($parts), $artifacts, true)) {
            array_pop($parts);
        }

        return implode(' ', $parts);
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<int, string>
     */
    private function warnings(array $parsed, string $text): array
    {
        $warnings = [];

        if (! isset($parsed['nik'])) {
            $warnings[] = 'missing_nik';
        } elseif (strlen((string) $parsed['nik']) !== 16) {
            $warnings[] = 'invalid_nik_length';
        }

        foreach (['name', 'address'] as $field) {
            if (! isset($parsed[$field])) {
                $warnings[] = 'missing_'.$field;
            }
        }

        $publicFieldCount = count(array_filter(
            array_keys($parsed),
            fn (string $field): bool => ! str_starts_with($field, '_'),
        ));

        if ($publicFieldCount < 4 && trim($text) !== '') {
            $warnings[] = 'low_field_count';
        }

        return array_values(array_unique($warnings));
    }
}
