<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;

class KtpOcrPrepareVerifiedDatasetCommand extends Command
{
    protected $signature = 'ktp:ocr-prepare-verified-dataset
        {--source=storage/app/private/ocr/ktp-dataset/raw : Source directory containing KTP images}
        {--output= : Output dataset directory}
        {--limit=20 : Maximum number of cases to stage}
        {--overwrite : Replace an existing output directory}';

    protected $description = 'Stage KTP images into a private manually verified OCR benchmark dataset.';

    public function handle(): int
    {
        $source = $this->absolutePath((string) $this->option('source'));
        $output = $this->option('output')
            ? $this->absolutePath((string) $this->option('output'))
            : storage_path('app/private/ocr/ktp-dataset/verified-'.now()->format('Ymd-His'));
        $limit = max(1, (int) $this->option('limit'));

        if (! is_dir($source)) {
            $this->error('Source directory does not exist: '.$source);

            return self::FAILURE;
        }

        if (is_dir($output) && ! $this->option('overwrite')) {
            $this->error('Output directory already exists. Pass --overwrite or choose another --output.');

            return self::FAILURE;
        }

        if (is_dir($output) && $this->option('overwrite')) {
            File::deleteDirectory($output);
        }

        File::ensureDirectoryExists($output);

        $images = array_slice($this->candidateImages($source), 0, $limit);

        $auditRows = ['case_id,image_path,expected_path,metadata_path,source_path,status'];

        foreach ($images as $index => $image) {
            $caseNumber = str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
            $caseId = 'case-'.$caseNumber;
            $caseDirectory = $output.'/case-'.$caseNumber;
            $extension = Str::lower($image->getExtension()) ?: 'jpg';
            $imagePath = $caseDirectory.'/original.'.$extension;
            $expectedPath = $caseDirectory.'/expected.json';
            $metadataPath = $caseDirectory.'/metadata.json';
            $sourcePath = str_replace(base_path().'/', '', $image->getPathname());

            File::ensureDirectoryExists($caseDirectory);
            File::copy($image->getPathname(), $imagePath);
            File::put($expectedPath, json_encode($this->emptyExpected(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
            File::put($metadataPath, json_encode([
                'source_path' => $sourcePath,
                'source_hash' => hash_file('sha256', $image->getPathname()),
                'document_guess' => 'ktp',
                'expected_fields_count' => 0,
                'has_expected' => true,
                'audit_status' => 'needs_review',
                'suggested_audit_status' => 'needs_review',
                'scored' => false,
                'audit_notes' => [
                    'manual_expected_required',
                    'copy_expected_fields_from_image',
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

            $auditRows[] = $this->csvRow([
                $caseId,
                $this->relativePath($imagePath),
                $this->relativePath($expectedPath),
                $this->relativePath($metadataPath),
                $sourcePath,
                'needs_expected',
            ]);
        }

        File::put($output.'/audit.csv', implode(PHP_EOL, $auditRows).PHP_EOL);
        File::put($output.'/README.md', $this->readme($output));

        $this->info('Verified OCR dataset staged.');
        $this->line('Directory: '.$output);
        $this->line('Cases: '.count($images));
        $this->warn('Fill each expected.json from the image itself, then set metadata audit_status to valid_ktp_expected_matches.');

        return self::SUCCESS;
    }

    private function absolutePath(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return base_path($path);
    }

    /**
     * @return array<int, SplFileInfo>
     */
    private function candidateImages(string $source): array
    {
        return collect(File::allFiles($source))
            ->filter(fn (SplFileInfo $file): bool => in_array(Str::lower($file->getExtension()), ['jpg', 'jpeg', 'png', 'webp'], true))
            ->filter(fn (SplFileInfo $file): bool => Str::startsWith(Str::lower($file->getBasename()), 'original.'))
            ->sortBy(fn (SplFileInfo $file): string => $file->getPathname())
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function emptyExpected(): array
    {
        return [
            'nik' => '',
            'name' => '',
            'address' => '',
            'rt' => '',
            'rw' => '',
            'village' => '',
            'district' => '',
            'city' => '',
            'province' => '',
        ];
    }

    private function readme(string $output): string
    {
        return <<<MARKDOWN
# Verified KTP OCR Dataset

This dataset is for strict OCR accuracy scoring.

Use `audit.csv` as the review checklist. It links each case to its image,
`expected.json`, and `metadata.json`.

For each `case-*` directory:

1. Open `original.*`.
2. Fill `expected.json` by copying the visible KTP fields from the image itself.
3. Set `metadata.json`:
   - `audit_status`: `valid_ktp_expected_matches`
   - `expected_fields_count`: the number of non-empty expected fields
   - `scored`: `true`
   - remove `manual_expected_required` from `audit_notes`

Run:

```bash
docker compose exec -T app php artisan ktp:ocr-benchmark --source=directory --input={$this->relativePath($output)} --engine=paddleocr --variant=normalized
```

Only manually verified cases should be used to judge strict OCR accuracy.
MARKDOWN;
    }

    /**
     * @param  array<int, string>  $values
     */
    private function csvRow(array $values): string
    {
        return implode(',', array_map(
            fn (string $value): string => '"'.str_replace('"', '""', $value).'"',
            $values,
        ));
    }

    private function relativePath(string $path): string
    {
        return str_replace(base_path().'/', '', $path);
    }
}
