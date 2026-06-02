<?php

namespace App\Services\Ocr;

use App\Models\Registration;
use App\Services\KtpOcrParser;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

class KtpOcrBenchmarkService
{
    public function __construct(
        private readonly KtpImagePreprocessor $preprocessor,
        private readonly KtpOcrParser $parser,
    ) {}

    /**
     * @param  array{source?: string, input?: string|null, limit?: int|null, engine?: string, variant?: string, psm?: string|int}  $options
     * @return array<string, mixed>
     */
    public function run(array $options = []): array
    {
        $source = $options['source'] ?? 'local';
        $limit = $options['limit'] ?? null;
        $engine = $this->engine($options['engine'] ?? 'paddleocr');
        $selectedVariant = $options['variant'] ?? 'all';
        $psmModes = $this->psmModes($options['psm'] ?? 6);
        $runId = now()->format('Ymd-His').'-'.Str::lower(Str::random(6));
        $reportRoot = storage_path('app/private/ocr/reports/'.$runId);

        File::ensureDirectoryExists($reportRoot);

        $images = $this->images($source, $options['input'] ?? null, $limit);
        $cases = [];

        foreach ($images as $index => $image) {
            $caseId = $this->caseId($image['path'], $index);
            $caseRoot = $reportRoot.'/cases/'.$caseId;
            File::ensureDirectoryExists($caseRoot);

            $variants = $this->preprocessor->variants($image['path'], $caseRoot);

            if ($selectedVariant !== 'all') {
                $variants = array_filter(
                    $variants,
                    fn (string $name): bool => $name === $selectedVariant,
                    ARRAY_FILTER_USE_KEY,
                );
            }

            $results = [];

            foreach ($variants as $variant => $path) {
                if ($engine === 'paddleocr') {
                    $resultKey = $variant.'_'.$engine;
                    $results[$resultKey] = $this->readVariant($path, $caseRoot, $resultKey, $variant, null, $image['expected'], $engine);

                    continue;
                }

                foreach ($psmModes as $psm) {
                    $resultKey = $variant.'_psm'.$psm;
                    $results[$resultKey] = $this->readVariant($path, $caseRoot, $resultKey, $variant, $psm, $image['expected'], $engine);
                }
            }

            $bestVariant = $this->bestVariant($results);
            $bestResult = $bestVariant ? ($results[$bestVariant] ?? []) : [];
            $audit = $this->auditCase($image['expected'], $bestResult, $image['metadata']);

            $this->writeAuditMetadata($image['path'], $image['metadata'], $image['expected'], $audit);

            $cases[] = [
                'case_id' => $caseId,
                'source' => $image['source'],
                'source_path' => $image['relative_path'],
                'image_hash' => hash_file('sha256', $image['path']),
                'has_expected' => $image['expected'] !== null,
                'expected' => $image['expected'],
                'metadata' => array_merge($image['metadata'] ?? [], $audit),
                'audit' => $audit,
                'best_variant' => $bestVariant,
                'results' => $results,
            ];
        }

        $report = [
            'run_id' => $runId,
            'generated_at' => now()->toIso8601String(),
            'source' => $source,
            'engine' => $engine,
            'psm_modes' => $psmModes,
            'case_count' => count($cases),
            'summary' => $this->summary($cases),
            'cases' => $cases,
        ];

        File::put($reportRoot.'/report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        File::put($reportRoot.'/report.csv', $this->csv($cases));
        File::put($reportRoot.'/field-accuracy.csv', $this->fieldAccuracyCsv($report['summary']));
        File::put($reportRoot.'/review.csv', $this->reviewCsv($cases));

        return [
            'run_id' => $runId,
            'report_path' => $reportRoot.'/report.json',
            'csv_path' => $reportRoot.'/report.csv',
            'field_accuracy_path' => $reportRoot.'/field-accuracy.csv',
            'review_path' => $reportRoot.'/review.csv',
            'case_count' => count($cases),
            'summary' => $report['summary'],
        ];
    }

    /**
     * @return array<int, array{source: string, path: string, relative_path: string, expected: array<string, mixed>|null, metadata: array<string, mixed>}>
     */
    private function images(string $source, ?string $input, ?int $limit): array
    {
        $images = match ($source) {
            'documents' => $this->documentImages(),
            'directory' => $this->directoryImages($input ?: storage_path('app/private/ocr/ktp-dataset')),
            default => array_merge(
                $this->documentImages(),
                $this->directoryImages(storage_path('app/private/ocr/ktp-dataset')),
                $this->directoryImages(storage_path('app/public/ktp')),
            ),
        };

        $unique = [];

        foreach ($images as $image) {
            if (! is_file($image['path'])) {
                continue;
            }

            $unique[$image['path']] = $image;
        }

        $images = array_values($unique);

        return $limit ? array_slice($images, 0, $limit) : $images;
    }

    /**
     * @return array<int, array{source: string, path: string, relative_path: string, expected: array<string, mixed>|null, metadata: array<string, mixed>}>
     */
    private function documentImages(): array
    {
        $images = [];

        Registration::query()
            ->get(['ktp_original_file_path', 'ktp_processed_file_path'])
            ->each(function (Registration $registration) use (&$images): void {
                foreach (['ktp_processed_file_path', 'ktp_original_file_path'] as $column) {
                    $path = $registration->{$column};

                    if (! $path) {
                        continue;
                    }

                    $images[] = [
                        'source' => 'documents',
                        'path' => Storage::disk('public')->path($path),
                        'relative_path' => 'public:'.$path,
                        'expected' => null,
                        'metadata' => [],
                    ];
                }
            });

        return $images;
    }

    /**
     * @return array<int, array{source: string, path: string, relative_path: string, expected: array<string, mixed>|null, metadata: array<string, mixed>}>
     */
    private function directoryImages(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        return collect(File::allFiles($directory))
            ->filter(fn ($file): bool => in_array(Str::lower($file->getExtension()), ['jpg', 'jpeg', 'png', 'webp'], true))
            ->map(fn ($file): array => [
                'source' => 'directory',
                'path' => $file->getPathname(),
                'relative_path' => str_replace(base_path().'/', '', $file->getPathname()),
                'expected' => $this->expectedForImage($file->getPathname()),
                'metadata' => $this->metadataForImage($file->getPathname()),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function readVariant(string $path, string $caseRoot, string $resultKey, string $variant, ?int $psm, ?array $expected, string $engine): array
    {
        try {
            $startedAt = hrtime(true);

            if ($engine === 'paddleocr') {
                $rawText = $this->runPaddleOcr($path);
                $language = 'paddleocr';
            } else {
                $rawText = $this->runTesseract($path, 'ind+eng', $psm ?? 6);
                $language = 'ind+eng';
            }
            $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);
        } catch (Throwable $exception) {
            $durationMs = isset($startedAt) ? (int) round((hrtime(true) - $startedAt) / 1_000_000) : null;

            if ($engine === 'paddleocr') {
                return $this->failedResult($variant, $psm, $engine, $exception->getMessage(), $durationMs);
            }

            try {
                $startedAt = hrtime(true);
                $rawText = $this->runTesseract($path, 'eng', $psm ?? 6);
                $language = 'eng';
                $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);
            } catch (Throwable $exception) {
                $durationMs = isset($startedAt) ? (int) round((hrtime(true) - $startedAt) / 1_000_000) : null;

                return $this->failedResult($variant, $psm, $engine, $exception->getMessage(), $durationMs);
            }
        }

        $parsed = $this->parser->parse($rawText);
        $warnings = $parsed['_warnings'] ?? [];
        unset($parsed['_warnings']);
        $comparison = $expected ? $this->compare($parsed, $expected) : null;
        $extractionScore = $this->score($parsed, $warnings);
        $rawTextLineCount = count($this->nonEmptyLines($rawText));

        $rawTextPath = $caseRoot.'/'.$resultKey.'.txt';
        File::put($rawTextPath, $rawText);

        return [
            'language' => $language,
            'variant' => $variant,
            'psm' => $psm,
            'engine' => $engine,
            'duration_ms' => $durationMs,
            'raw_text_path' => $rawTextPath,
            'raw_text_line_count' => $rawTextLineCount,
            'parsed' => $parsed,
            'score' => $comparison['score'] ?? $extractionScore,
            'extraction_score' => $extractionScore,
            'accuracy' => $comparison['accuracy'] ?? null,
            'comparison' => $comparison,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function failedResult(string $variant, ?int $psm, string $engine, string $message, ?int $durationMs = null): array
    {
        return [
            'language' => null,
            'variant' => $variant,
            'psm' => $psm,
            'engine' => $engine,
            'duration_ms' => $durationMs,
            'raw_text_path' => null,
            'raw_text_line_count' => 0,
            'parsed' => ['ocr_error' => $message],
            'score' => 0,
            'extraction_score' => 0,
            'accuracy' => null,
            'comparison' => null,
            'warnings' => ['ocr_failed'],
        ];
    }

    private function runPaddleOcr(string $path): string
    {
        $baseUrl = rtrim((string) config('services.paddleocr.url'), '/');

        if ($baseUrl === '') {
            throw new \RuntimeException('PaddleOCR service URL is not configured.');
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new \RuntimeException('KTP image could not be read.');
        }

        $response = Http::timeout((int) config('services.paddleocr.timeout'))
            ->attach('image', $contents, basename($path))
            ->post($baseUrl.'/ktp/read');

        if (! $response->successful()) {
            throw new \RuntimeException($response->json('detail') ?: 'PaddleOCR failed.');
        }

        $decoded = $response->json();

        if (! is_array($decoded) || ! isset($decoded['text']) || ! is_string($decoded['text'])) {
            throw new \RuntimeException('PaddleOCR returned invalid JSON.');
        }

        return trim($decoded['text']);
    }

    private function runTesseract(string $path, string $language, int $psm): string
    {
        $process = new Process(['tesseract', $path, 'stdout', '-l', $language, '--psm', (string) $psm]);
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException(trim($process->getErrorOutput()) ?: 'Tesseract OCR failed.');
        }

        return trim($process->getOutput());
    }

    /**
     * @param  array<string, array<string, mixed>>  $results
     */
    private function bestVariant(array $results): ?string
    {
        $best = null;
        $bestScore = -1;
        $bestExtractionScore = -1;
        $bestWarningCount = PHP_INT_MAX;

        foreach ($results as $variant => $result) {
            $score = $result['score'] ?? 0;
            $extractionScore = $result['extraction_score'] ?? $score;
            $warningCount = count($result['warnings'] ?? []);

            if (
                $score > $bestScore
                || ($score === $bestScore && $extractionScore > $bestExtractionScore)
                || ($score === $bestScore && $extractionScore === $bestExtractionScore && $warningCount < $bestWarningCount)
            ) {
                $best = $variant;
                $bestScore = $score;
                $bestExtractionScore = $extractionScore;
                $bestWarningCount = $warningCount;
            }
        }

        return $best;
    }

    /**
     * @return array<int, int>
     */
    private function psmModes(string|int $psm): array
    {
        if ((string) $psm === 'all') {
            return [6, 11];
        }

        $selected = (int) $psm;

        return in_array($selected, [6, 11], true) ? [$selected] : [6];
    }

    private function engine(string $engine): string
    {
        return in_array($engine, ['paddleocr', 'tesseract'], true) ? $engine : 'paddleocr';
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
            'birth_place_date' => 2,
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

    /**
     * @param  array<int, array<string, mixed>>  $cases
     * @return array<string, mixed>
     */
    private function summary(array $cases): array
    {
        $fields = ['nik', 'name', 'birth_place_date', 'address', 'rt', 'rw', 'village', 'district', 'province', 'city'];
        $summary = [
            'field_hits' => array_fill_keys($fields, 0),
            'warnings' => [],
            'expected_cases' => 0,
            'field_accuracy' => array_fill_keys($fields, ['correct' => 0, 'total' => 0, 'accuracy' => null]),
            'scored_cases' => 0,
            'unscored_cases' => 0,
            'excluded_cases' => 0,
            'excluded_by_status' => [],
            'scored_field_accuracy' => array_fill_keys($fields, ['correct' => 0, 'total' => 0, 'accuracy' => null]),
            'best_variants' => [],
            'mismatches_by_field' => array_fill_keys($fields, 0),
            'scored_mismatches_by_field' => array_fill_keys($fields, 0),
            'audit_notes' => [],
            'duration_ms' => [
                'count' => 0,
                'average' => null,
                'median' => null,
                'min' => null,
                'max' => null,
            ],
            'average_accuracy' => null,
            'scored_average_accuracy' => null,
        ];
        $accuracyTotal = 0.0;
        $accuracyCount = 0;
        $scoredAccuracyTotal = 0.0;
        $scoredAccuracyCount = 0;
        $durations = [];

        foreach ($cases as $case) {
            $best = $case['best_variant'];
            $result = $best ? ($case['results'][$best] ?? []) : [];
            $parsed = $result['parsed'] ?? [];
            $comparison = $result['comparison'] ?? null;
            $isScored = (bool) ($case['audit']['scored'] ?? false);
            $auditStatus = (string) ($case['audit']['audit_status'] ?? 'needs_review');

            if ($best) {
                $summary['best_variants'][$best] = ($summary['best_variants'][$best] ?? 0) + 1;
            }

            if (is_numeric($result['duration_ms'] ?? null)) {
                $durations[] = (int) $result['duration_ms'];
            }

            if ($isScored) {
                $summary['scored_cases']++;
            } else {
                $summary['unscored_cases']++;
                $summary['excluded_cases']++;
                $summary['excluded_by_status'][$auditStatus] = ($summary['excluded_by_status'][$auditStatus] ?? 0) + 1;
            }

            foreach ($fields as $field) {
                if (filled($parsed[$field] ?? null)) {
                    $summary['field_hits'][$field]++;
                }

                if (($comparison['fields'][$field]['expected_present'] ?? false) === true) {
                    $summary['field_accuracy'][$field]['total']++;

                    if (($comparison['fields'][$field]['matched'] ?? false) === true) {
                        $summary['field_accuracy'][$field]['correct']++;
                    } else {
                        $summary['mismatches_by_field'][$field]++;
                    }

                    if ($isScored) {
                        $summary['scored_field_accuracy'][$field]['total']++;

                        if (($comparison['fields'][$field]['matched'] ?? false) === true) {
                            $summary['scored_field_accuracy'][$field]['correct']++;
                        } else {
                            $summary['scored_mismatches_by_field'][$field]++;
                        }
                    }
                }
            }

            if ($comparison !== null) {
                $summary['expected_cases']++;
                $accuracyTotal += (float) ($result['accuracy'] ?? 0);
                $accuracyCount++;

                if ($isScored) {
                    $scoredAccuracyTotal += (float) ($result['accuracy'] ?? 0);
                    $scoredAccuracyCount++;
                }
            }

            foreach (($result['warnings'] ?? []) as $warning) {
                $summary['warnings'][$warning] = ($summary['warnings'][$warning] ?? 0) + 1;
            }

            foreach (($case['audit']['audit_notes'] ?? []) as $note) {
                $summary['audit_notes'][$note] = ($summary['audit_notes'][$note] ?? 0) + 1;
            }
        }

        foreach ($summary['field_accuracy'] as $field => $stats) {
            $summary['field_accuracy'][$field]['accuracy'] = $stats['total'] > 0
                ? round($stats['correct'] / $stats['total'], 4)
                : null;
        }

        foreach ($summary['scored_field_accuracy'] as $field => $stats) {
            $summary['scored_field_accuracy'][$field]['accuracy'] = $stats['total'] > 0
                ? round($stats['correct'] / $stats['total'], 4)
                : null;
        }

        $summary['average_accuracy'] = $accuracyCount > 0 ? round($accuracyTotal / $accuracyCount, 4) : null;
        $summary['scored_average_accuracy'] = $scoredAccuracyCount > 0 ? round($scoredAccuracyTotal / $scoredAccuracyCount, 4) : null;
        $summary['duration_ms'] = $this->durationStats($durations);

        return $summary;
    }

    /**
     * @param  array<int, int>  $durations
     * @return array{count: int, average: int|null, median: int|null, min: int|null, max: int|null}
     */
    private function durationStats(array $durations): array
    {
        sort($durations);
        $count = count($durations);

        if ($count === 0) {
            return [
                'count' => 0,
                'average' => null,
                'median' => null,
                'min' => null,
                'max' => null,
            ];
        }

        $middle = intdiv($count, 2);
        $median = $count % 2 === 1
            ? $durations[$middle]
            : (int) round(($durations[$middle - 1] + $durations[$middle]) / 2);

        return [
            'count' => $count,
            'average' => (int) round(array_sum($durations) / $count),
            'median' => $median,
            'min' => $durations[0],
            'max' => $durations[$count - 1],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $cases
     */
    private function csv(array $cases): string
    {
        $rows = ['case_id,source,best_variant,audit_status,scored,score,extraction_score,accuracy,duration_ms,raw_text_lines,nik,name,address,mismatches,warnings,audit_notes'];

        foreach ($cases as $case) {
            $best = $case['best_variant'];
            $result = $best ? ($case['results'][$best] ?? []) : [];
            $parsed = $result['parsed'] ?? [];
            $mismatches = collect($result['comparison']['fields'] ?? [])
                ->filter(fn (array $field): bool => ($field['expected_present'] ?? false) && ! ($field['matched'] ?? false))
                ->keys()
                ->implode('|');

            $rows[] = implode(',', array_map(
                fn (string $value): string => '"'.str_replace('"', '""', $value).'"',
                [
                    $case['case_id'],
                    $case['source_path'],
                    (string) $best,
                    (string) ($case['audit']['audit_status'] ?? 'needs_review'),
                    ($case['audit']['scored'] ?? false) ? '1' : '0',
                    (string) ($result['score'] ?? 0),
                    (string) ($result['extraction_score'] ?? 0),
                    $result['accuracy'] === null ? '' : (string) $result['accuracy'],
                    is_numeric($result['duration_ms'] ?? null) ? (string) $result['duration_ms'] : '',
                    (string) ($result['raw_text_line_count'] ?? 0),
                    $this->maskNik((string) ($parsed['nik'] ?? '')),
                    (string) ($parsed['name'] ?? ''),
                    (string) ($parsed['address'] ?? ''),
                    $mismatches,
                    implode('|', $result['warnings'] ?? []),
                    implode('|', $case['audit']['audit_notes'] ?? []),
                ],
            ));
        }

        return implode(PHP_EOL, $rows).PHP_EOL;
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function fieldAccuracyCsv(array $summary): string
    {
        $rows = ['field,correct,total,accuracy'];

        foreach (($summary['field_accuracy'] ?? []) as $field => $stats) {
            $rows[] = implode(',', [
                $field,
                (string) ($stats['correct'] ?? 0),
                (string) ($stats['total'] ?? 0),
                $stats['accuracy'] === null ? '' : (string) $stats['accuracy'],
            ]);
        }

        $rows[] = '';
        $rows[] = 'scored_field,correct,total,accuracy';

        foreach (($summary['scored_field_accuracy'] ?? []) as $field => $stats) {
            $rows[] = implode(',', [
                $field,
                (string) ($stats['correct'] ?? 0),
                (string) ($stats['total'] ?? 0),
                $stats['accuracy'] === null ? '' : (string) $stats['accuracy'],
            ]);
        }

        return implode(PHP_EOL, $rows).PHP_EOL;
    }

    /**
     * @param  array<int, array<string, mixed>>  $cases
     */
    private function reviewCsv(array $cases): string
    {
        usort($cases, function (array $left, array $right): int {
            $leftResult = $left['best_variant'] ? ($left['results'][$left['best_variant']] ?? []) : [];
            $rightResult = $right['best_variant'] ? ($right['results'][$right['best_variant']] ?? []) : [];

            return [
                $leftResult['accuracy'] ?? 1,
                -($leftResult['extraction_score'] ?? 0),
            ] <=> [
                $rightResult['accuracy'] ?? 1,
                -($rightResult['extraction_score'] ?? 0),
            ];
        });

        $rows = ['case_id,source,best_variant,audit_status,scored,suggested_audit_status,accuracy,extraction_score,duration_ms,raw_text_lines,mismatches,nik_expected,nik_actual,name_expected,name_actual,address_expected,address_actual,mismatch_reasons,audit_notes,warnings'];

        foreach ($cases as $case) {
            $best = $case['best_variant'];
            $result = $best ? ($case['results'][$best] ?? []) : [];
            $fields = $result['comparison']['fields'] ?? [];
            $mismatches = collect($fields)
                ->filter(fn (array $field): bool => ($field['expected_present'] ?? false) && ! ($field['matched'] ?? false))
                ->keys()
                ->implode('|');
            $reasons = collect($fields)
                ->filter(fn (array $field): bool => ($field['expected_present'] ?? false) && ! ($field['matched'] ?? false))
                ->map(fn (array $field, string $name): string => $name.':'.($field['reason'] ?? 'unknown'))
                ->values()
                ->implode('|');

            $rows[] = implode(',', array_map(
                fn (string $value): string => '"'.str_replace('"', '""', $value).'"',
                [
                    $case['case_id'],
                    $case['source_path'],
                    (string) $best,
                    (string) ($case['audit']['audit_status'] ?? 'needs_review'),
                    ($case['audit']['scored'] ?? false) ? '1' : '0',
                    (string) ($case['audit']['suggested_audit_status'] ?? 'needs_review'),
                    $result['accuracy'] === null ? '' : (string) $result['accuracy'],
                    (string) ($result['extraction_score'] ?? 0),
                    is_numeric($result['duration_ms'] ?? null) ? (string) $result['duration_ms'] : '',
                    (string) ($result['raw_text_line_count'] ?? 0),
                    $mismatches,
                    $this->maskNik((string) ($fields['nik']['expected'] ?? '')),
                    (string) ($fields['nik']['actual'] ?? ''),
                    (string) ($fields['name']['expected'] ?? ''),
                    (string) ($fields['name']['actual'] ?? ''),
                    (string) ($fields['address']['expected'] ?? ''),
                    (string) ($fields['address']['actual'] ?? ''),
                    $reasons,
                    implode('|', $case['audit']['audit_notes'] ?? []),
                    implode('|', $result['warnings'] ?? []),
                ],
            ));
        }

        return implode(PHP_EOL, $rows).PHP_EOL;
    }

    private function caseId(string $path, int $index): string
    {
        return str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT).'-'.substr(hash_file('sha1', $path), 0, 10);
    }

    private function maskNik(string $nik): string
    {
        if (strlen($nik) < 8) {
            return $nik;
        }

        return substr($nik, 0, 6).str_repeat('*', max(0, strlen($nik) - 10)).substr($nik, -4);
    }

    /**
     * @return array<int, string>
     */
    private function nonEmptyLines(string $text): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split('/\R/u', $text) ?: []),
            fn (string $line): bool => $line !== '',
        ));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function expectedForImage(string $path): ?array
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $sameBasename = substr($path, 0, -strlen($extension)).'json';
        $sameDirectory = dirname($path).'/expected.json';

        foreach ([$sameBasename, $sameDirectory] as $candidate) {
            if (! is_file($candidate)) {
                continue;
            }

            $decoded = json_decode((string) file_get_contents($candidate), true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function metadataForImage(string $path): array
    {
        $candidate = dirname($path).'/metadata.json';

        if (! is_file($candidate)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($candidate), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>|null  $expected
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $metadata
     * @return array{document_guess: string, expected_fields_count: int, audit_status: string, suggested_audit_status: string, scored: bool, audit_notes: array<int, string>}
     */
    private function auditCase(?array $expected, array $result, array $metadata): array
    {
        $parsed = $result['parsed'] ?? [];
        $rawTextLines = (int) ($result['raw_text_line_count'] ?? 0);
        $notes = array_values(array_filter(
            $metadata['audit_notes'] ?? [],
            fn (mixed $note): bool => is_string($note) && $note !== '',
        ));
        $documentGuess = 'unknown';
        $existingStatus = (string) ($metadata['audit_status'] ?? '');

        if (filled($parsed['nik'] ?? null) || str_contains(Str::upper(json_encode($parsed) ?: ''), 'PROVINSI')) {
            $documentGuess = 'ktp';
        } elseif ($rawTextLines > 0) {
            $documentGuess = 'non_ktp_or_low_quality';
            $notes[] = 'document_guess_not_ktp';
        }

        if ($rawTextLines < 3) {
            $notes[] = 'very_low_ocr_text';
        }

        $expectedFieldsCount = count(array_filter($expected ?? [], fn ($value): bool => filled($value)));

        if ($expectedFieldsCount > 0 && ($result['accuracy'] ?? null) === 0.0 && ($result['extraction_score'] ?? 0) >= 6) {
            $notes[] = 'possible_expected_mismatch';
        }

        foreach (($result['comparison']['fields'] ?? []) as $field => $comparison) {
            if (($comparison['expected_present'] ?? false) && ($comparison['reason'] ?? null) === 'value_mismatch') {
                $notes[] = 'mismatch_'.$field;
            }
        }

        $validStatuses = [
            'valid_ktp_expected_matches',
            'valid_ktp_expected_mismatch',
            'non_ktp_document',
            'unreadable_image',
            'needs_review',
        ];
        $suggestedAuditStatus = match (true) {
            $rawTextLines < 3 => 'unreadable_image',
            $documentGuess !== 'ktp' => 'non_ktp_document',
            in_array('possible_expected_mismatch', $notes, true) => 'valid_ktp_expected_mismatch',
            ($result['accuracy'] ?? null) === 1.0 => 'valid_ktp_expected_matches',
            default => 'needs_review',
        };
        $auditStatus = in_array($existingStatus, $validStatuses, true) && $existingStatus !== 'needs_review'
            ? $existingStatus
            : $suggestedAuditStatus;

        if ($auditStatus === 'needs_review') {
            $notes[] = 'needs_manual_audit';
        } else {
            $notes = array_values(array_diff($notes, ['needs_manual_audit']));
        }

        $scored = $auditStatus === 'valid_ktp_expected_matches';

        return [
            'document_guess' => $documentGuess,
            'expected_fields_count' => $expectedFieldsCount,
            'audit_status' => $auditStatus,
            'suggested_audit_status' => $suggestedAuditStatus,
            'scored' => $scored,
            'audit_notes' => array_values(array_unique($notes)),
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>|null  $expected
     * @param  array<string, mixed>  $audit
     */
    private function writeAuditMetadata(string $imagePath, array $metadata, ?array $expected, array $audit): void
    {
        if (
            ! str_contains($imagePath, storage_path('app/private/ocr/ktp-dataset'))
            && ! str_contains($imagePath, 'storage/app/private/ocr/ktp-dataset')
        ) {
            return;
        }

        $path = dirname($imagePath).'/metadata.json';
        $payload = array_merge($metadata, [
            'document_guess' => $audit['document_guess'],
            'expected_fields_count' => $audit['expected_fields_count'],
            'audit_status' => $audit['audit_status'],
            'suggested_audit_status' => $audit['suggested_audit_status'],
            'scored' => $audit['scored'],
            'audit_notes' => $audit['audit_notes'],
            'has_expected' => $expected !== null,
        ]);

        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $expected
     * @return array{score: int, accuracy: float, fields: array<string, array{expected_present: bool, matched: bool, actual: string|null}>}
     */
    private function compare(array $parsed, array $expected): array
    {
        $fields = ['nik', 'name', 'birth_place_date', 'address', 'rt', 'rw', 'village', 'district', 'province', 'city'];
        $comparison = [];
        $correct = 0;
        $total = 0;

        foreach ($fields as $field) {
            $expectedValue = $expected[$field] ?? null;
            $actualValue = $parsed[$field] ?? null;
            $expectedPresent = filled($expectedValue);
            $normalizedActual = $this->normalizeComparable((string) $actualValue, $field);
            $normalizedExpected = $this->normalizeComparable((string) $expectedValue, $field);
            $matched = $expectedPresent && $normalizedActual === $normalizedExpected;
            $reason = match (true) {
                ! $expectedPresent => 'expected_missing',
                blank($actualValue) => 'missing_actual',
                $matched => 'matched',
                $normalizedActual === '' => 'empty_after_normalization',
                default => 'value_mismatch',
            };

            if ($expectedPresent) {
                $total++;

                if ($matched) {
                    $correct++;
                }
            }

            $comparison[$field] = [
                'expected_present' => $expectedPresent,
                'matched' => $matched,
                'reason' => $reason,
                'expected' => $field === 'nik' ? $this->maskNik((string) $expectedValue) : ($expectedValue ? (string) $expectedValue : null),
                'actual' => $field === 'nik' ? $this->maskNik((string) $actualValue) : ($actualValue ? (string) $actualValue : null),
            ];
        }

        return [
            'score' => $correct * 10,
            'accuracy' => $total > 0 ? round($correct / $total, 4) : 0.0,
            'fields' => $comparison,
        ];
    }

    private function normalizeComparable(string $value, string $field): string
    {
        $value = strtoupper(trim($value));

        if (in_array($field, ['nik', 'rt', 'rw'], true)) {
            return preg_replace('/\D+/', '', $value) ?? '';
        }

        $value = preg_replace('/[^A-Z0-9]+/', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }
}
