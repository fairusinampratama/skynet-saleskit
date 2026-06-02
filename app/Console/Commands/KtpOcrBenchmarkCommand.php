<?php

namespace App\Console\Commands;

use App\Services\Ocr\KtpOcrBenchmarkService;
use Illuminate\Console\Command;

class KtpOcrBenchmarkCommand extends Command
{
    protected $signature = 'ktp:ocr-benchmark
        {--source=local : local, documents, atau directory}
        {--input= : Direktori input saat source=directory}
        {--limit= : Jumlah gambar maksimum}
        {--engine=paddleocr : Mesin OCR: paddleocr atau tesseract}
        {--variant=all : all, original, normalized, upscaled, high_contrast, threshold, atau sharpened_threshold}
        {--psm=6 : Mode segmentasi halaman Tesseract: 6, 11, atau all}';

    protected $description = 'Jalankan OCR KTP pada sampel gambar lokal/privat dan tulis laporan benchmark privat.';

    public function handle(KtpOcrBenchmarkService $benchmark): int
    {
        $limit = $this->option('limit');

        $result = $benchmark->run([
            'source' => (string) $this->option('source'),
            'input' => $this->option('input') ? (string) $this->option('input') : null,
            'limit' => $limit ? (int) $limit : null,
            'engine' => (string) $this->option('engine'),
            'variant' => (string) $this->option('variant'),
            'psm' => (string) $this->option('psm'),
        ]);

        $this->info('Benchmark OCR KTP selesai.');
        $this->line('ID proses: '.$result['run_id']);
        $this->line('Jumlah kasus: '.$result['case_count']);
        $this->line('JSON: '.$result['report_path']);
        $this->line('CSV: '.$result['csv_path']);
        $this->line('CSV akurasi field: '.$result['field_accuracy_path']);
        $this->line('CSV tinjauan: '.$result['review_path']);

        foreach (($result['summary']['field_hits'] ?? []) as $field => $hits) {
            $this->line($field.': '.$hits);
        }

        $this->newLine();
        $this->line('Gerbang penilaian:');
        $this->line('kasus_dinilai: '.($result['summary']['scored_cases'] ?? 0));
        $this->line('kasus_dikecualikan: '.($result['summary']['excluded_cases'] ?? 0));

        if (($result['summary']['excluded_by_status'] ?? []) !== []) {
            $this->line('dikecualikan_berdasarkan_status: '.json_encode($result['summary']['excluded_by_status']));
        }

        if (($result['summary']['duration_ms']['count'] ?? 0) > 0) {
            $this->newLine();
            $this->line('Durasi OCR:');
            $this->line('rata_rata_ms: '.$result['summary']['duration_ms']['average']);
            $this->line('median_ms: '.$result['summary']['duration_ms']['median']);
        }

        if (($result['summary']['expected_cases'] ?? 0) > 0) {
            $this->newLine();
            $this->line('Akurasi field tanpa skor:');

            foreach (($result['summary']['field_accuracy'] ?? []) as $field => $stats) {
                if (($stats['total'] ?? 0) === 0) {
                    continue;
                }

                $accuracy = number_format((float) ($stats['accuracy'] ?? 0) * 100, 2, '.', '');
                $this->line(sprintf(
                    '%s: %d/%d benar (%s%%)',
                    $field,
                    $stats['correct'] ?? 0,
                    $stats['total'] ?? 0,
                    rtrim(rtrim((string) $accuracy, '0'), '.'),
                ));
            }
        }

        if (($result['summary']['scored_cases'] ?? 0) > 0) {
            $this->newLine();
            $this->line('Akurasi field berskor:');

            foreach (($result['summary']['scored_field_accuracy'] ?? []) as $field => $stats) {
                if (($stats['total'] ?? 0) === 0) {
                    continue;
                }

                $accuracy = number_format((float) ($stats['accuracy'] ?? 0) * 100, 2, '.', '');
                $this->line(sprintf(
                    '%s: %d/%d benar (%s%%)',
                    $field,
                    $stats['correct'] ?? 0,
                    $stats['total'] ?? 0,
                    rtrim(rtrim((string) $accuracy, '0'), '.'),
                ));
            }
        }

        if (($result['summary']['warnings'] ?? []) !== []) {
            $this->warn('Peringatan: '.json_encode($result['summary']['warnings']));
        }

        if (($result['summary']['audit_notes'] ?? []) !== []) {
            $this->warn('Catatan audit: '.json_encode($result['summary']['audit_notes']));
        }

        return self::SUCCESS;
    }
}
