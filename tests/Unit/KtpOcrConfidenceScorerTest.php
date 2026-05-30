<?php

namespace Tests\Unit;

use App\Services\Ocr\KtpOcrConfidenceScorer;
use PHPUnit\Framework\TestCase;

class KtpOcrConfidenceScorerTest extends TestCase
{
    public function test_it_marks_consensus_fields_high_confidence(): void
    {
        $parsed = [
            'nik' => '3507072001800002',
            'name' => 'SLAMET',
            'address' => 'DUSUN ARAN-ARAN',
        ];
        $variants = [
            'original' => ['parsed' => $parsed],
            'normalized' => ['parsed' => $parsed],
        ];

        $confidence = (new KtpOcrConfidenceScorer)->score($parsed, $variants);

        $this->assertSame('high', $confidence['fields']['nik']);
        $this->assertSame('high', $confidence['fields']['name']);
        $this->assertSame('high', $confidence['fields']['address']);
        $this->assertSame('good_scan', $confidence['status']);
    }

    public function test_it_requires_confirmation_for_single_variant_fields(): void
    {
        $parsed = [
            'nik' => '3507072001800002',
            'name' => 'SLAMET',
        ];

        $confidence = (new KtpOcrConfidenceScorer)->score($parsed);

        $this->assertSame('medium', $confidence['fields']['nik']);
        $this->assertSame('medium', $confidence['fields']['name']);
        $this->assertSame('needs_confirmation', $confidence['status']);
    }

    public function test_it_marks_invalid_or_missing_critical_fields_low(): void
    {
        $confidence = (new KtpOcrConfidenceScorer)->score([
            'nik' => '350707200180',
            'address' => 'DUSUN ARAN-ARAN',
        ]);

        $this->assertSame('low', $confidence['fields']['nik']);
        $this->assertSame('low', $confidence['fields']['name']);
        $this->assertSame('manual_entry_required', $confidence['status']);
        $this->assertContains('nik_needs_confirmation', $confidence['warnings']);
    }
}
