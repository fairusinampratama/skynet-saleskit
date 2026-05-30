<?php

namespace App\Services\Ocr;

class KtpOcrConfidenceScorer
{
    /** @var array<int, string> */
    private array $targetFields = ['nik', 'name', 'address', 'rt', 'rw', 'village', 'district', 'city', 'province'];

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<string, array<string, mixed>>  $variantResults
     * @return array{fields: array<string, string>, overall: string, status: string, warnings: array<int, string>}
     */
    public function score(array $parsed, array $variantResults = []): array
    {
        $warnings = [];
        $fields = [];

        foreach ($this->targetFields as $field) {
            $value = $parsed[$field] ?? null;

            if (! filled($value)) {
                $fields[$field] = 'low';
                continue;
            }

            $agreement = $this->agreementCount($field, (string) $value, $variantResults);
            $fields[$field] = match (true) {
                $field === 'nik' && ! preg_match('/^\d{16}$/', (string) $value) => 'low',
                $field === 'nik' && $agreement >= 2 => 'high',
                $field === 'nik' => 'medium',
                in_array($field, ['name', 'address'], true) && mb_strlen((string) $value) < 5 => 'low',
                $agreement >= 2 => 'high',
                default => 'medium',
            };
        }

        if (($fields['nik'] ?? 'low') !== 'high') {
            $warnings[] = 'nik_needs_confirmation';
        }

        if (($fields['name'] ?? 'low') === 'low') {
            $warnings[] = 'name_needs_manual_entry';
        }

        if (($fields['address'] ?? 'low') === 'low') {
            $warnings[] = 'address_needs_manual_entry';
        }

        $highCount = count(array_filter($fields, fn (string $confidence): bool => $confidence === 'high'));
        $mediumCount = count(array_filter($fields, fn (string $confidence): bool => $confidence === 'medium'));

        $overall = match (true) {
            ($fields['nik'] ?? 'low') === 'high' && ($fields['name'] ?? 'low') !== 'low' && $highCount >= 3 => 'high',
            ($fields['nik'] ?? 'low') !== 'low' && ($fields['name'] ?? 'low') !== 'low' => 'medium',
            $highCount + $mediumCount >= 3 => 'medium',
            default => 'low',
        };

        return [
            'fields' => $fields,
            'overall' => $overall,
            'status' => match ($overall) {
                'high' => 'good_scan',
                'medium' => 'needs_confirmation',
                default => 'manual_entry_required',
            },
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $variantResults
     */
    private function agreementCount(string $field, string $value, array $variantResults): int
    {
        if ($variantResults === []) {
            return 1;
        }

        $expected = $this->normalize($value, $field);
        $count = 0;

        foreach ($variantResults as $result) {
            $candidate = $result['parsed'][$field] ?? null;

            if (filled($candidate) && $this->normalize((string) $candidate, $field) === $expected) {
                $count++;
            }
        }

        return $count;
    }

    private function normalize(string $value, string $field): string
    {
        $value = strtoupper(trim($value));

        if (in_array($field, ['nik', 'rt', 'rw'], true)) {
            return preg_replace('/\D+/', '', $value) ?? '';
        }

        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^A-Z0-9]+/', ' ', $value) ?? $value) ?? $value);
    }
}
