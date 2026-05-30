<?php

namespace Tests\Unit;

use App\Services\KtpOcrParser;
use PHPUnit\Framework\TestCase;

class KtpOcrParserTest extends TestCase
{
    public function test_it_extracts_common_ktp_fields_from_ocr_text(): void
    {
        $text = <<<'TEXT'
        PROVINSI JAWA TIMUR
        KABUPATEN MALANG
        NIK : 3507182709770004
        Nama : SUGENG HARIADI
        Tempat/Tgl Lahir : MALANG, 27-09-1977
        Jenis Kelamin : LAKI-LAKI   Gol. Darah : -
        Alamat : LOWOK SURUH
        RT/RW : 001 / 010
        Kel/Desa : MANGLIAWAN
        Kecamatan : PAKIS
        Agama : ISLAM
        Status Perkawinan : KAWIN
        Pekerjaan : KARYAWAN SWASTA
        Kewarganegaraan : WNI
        TEXT;

        $parsed = (new KtpOcrParser)->parse($text);

        $this->assertSame('3507182709770004', $parsed['nik']);
        $this->assertSame('JAWA TIMUR', $parsed['province']);
        $this->assertSame('KABUPATEN MALANG', $parsed['city']);
        $this->assertSame('SUGENG HARIADI', $parsed['name']);
        $this->assertSame('MALANG, 27-09-1977', $parsed['birth_place_date']);
        $this->assertSame('LAKI-LAKI', $parsed['gender']);
        $this->assertSame('LOWOK SURUH', $parsed['address']);
        $this->assertSame('001', $parsed['rt']);
        $this->assertSame('010', $parsed['rw']);
        $this->assertSame('MANGLIAWAN', $parsed['village']);
        $this->assertSame('PAKIS', $parsed['district']);
        $this->assertSame('ISLAM', $parsed['religion']);
        $this->assertSame('KAWIN', $parsed['marital_status']);
        $this->assertSame('KARYAWAN SWASTA', $parsed['occupation']);
        $this->assertSame('WNI', $parsed['nationality']);
    }

    public function test_it_extracts_fields_from_noisy_realistic_ocr_text(): void
    {
        $text = <<<'TEXT'
        | PROVINSI JAWA TIMUR
        KABUPATEN MALANG |
        NIK ! 35071627097 70004 pil
        Nama ' SUGENG HARIADI -
        Tempat/Tgi Lahir : MALANG, 27-09-1977 | = 'B
        . Jenis Kelamin LAKI-LAKI Gol. Darah : - -
        Alamat .LOWOK SURUH , |
        RTRW 001 / O10 | 4
        Kel/Desa ' MANGLIAWAN ui
        Kecamatan pakis |
        Agama ISLAM
        TEXT;

        $parsed = (new KtpOcrParser)->parse($text);

        $this->assertSame('3507162709770004', $parsed['nik']);
        $this->assertSame('JAWA TIMUR', $parsed['province']);
        $this->assertSame('KABUPATEN MALANG', $parsed['city']);
        $this->assertSame('SUGENG HARIADI', $parsed['name']);
        $this->assertSame('MALANG, 27-09-1977', $parsed['birth_place_date']);
        $this->assertSame('LOWOK SURUH', $parsed['address']);
        $this->assertSame('001', $parsed['rt']);
        $this->assertSame('010', $parsed['rw']);
        $this->assertSame('MANGLIAWAN', $parsed['village']);
        $this->assertSame('PAKIS', $parsed['district']);
    }

    public function test_it_extracts_rt_rw_from_browser_processed_ocr_noise(): void
    {
        $text = <<<'TEXT'
        Alamat : LOWOK SURUH i
        RT/RW 1001 1 010
        Kel/Desa ' MANGLIAWAN ies
        Kecamatan : pakis !
        TEXT;

        $parsed = (new KtpOcrParser)->parse($text);

        $this->assertSame('001', $parsed['rt']);
        $this->assertSame('010', $parsed['rw']);
    }

    public function test_it_reports_quality_warnings_for_weak_ocr_results(): void
    {
        $parsed = (new KtpOcrParser)->parse('NIK 350701010101 Nama BUDI');

        $this->assertContains('invalid_nik_length', $parsed['_warnings']);
        $this->assertContains('missing_address', $parsed['_warnings']);
        $this->assertContains('low_field_count', $parsed['_warnings']);
    }

    public function test_it_handles_alternate_ktp_label_noise(): void
    {
        $text = <<<'TEXT'
        5 PROVINSI JAWA BARAT
        KOTA BANDUNG
        NIK 3273010101010001
        N4MA : BUDI SANTOSO
        TEMPAT TGI LAHIR BANDUNG, 01-01-2001
        ALAMAI : JL MELATI NO 1
        RT RW 2 3
        KELDESA : CIBADAK
        KEC : ASTANAANYAR
        TEXT;

        $parsed = (new KtpOcrParser)->parse($text);

        $this->assertSame('BUDI SANTOSO', $parsed['name']);
        $this->assertSame('BANDUNG, 01-01-2001', $parsed['birth_place_date']);
        $this->assertSame('JL MELATI NO 1', $parsed['address']);
        $this->assertSame('002', $parsed['rt']);
        $this->assertSame('003', $parsed['rw']);
        $this->assertSame('CIBADAK', $parsed['village']);
        $this->assertSame('ASTANAANYAR', $parsed['district']);
    }

    public function test_it_infers_name_from_line_order_when_name_label_is_missing(): void
    {
        $text = <<<'TEXT'
        PROVINSI JAWA TIMUR
        KABUPATEN MALANG
        NIK : 3507182709770004
        SUGENG HARIADI
        Tempat/Tgl Lahir : MALANG, 27-09-1977
        Alamat : LOWOK SURUH
        TEXT;

        $parsed = (new KtpOcrParser)->parse($text);

        $this->assertSame('SUGENG HARIADI', $parsed['name']);
    }

    public function test_it_extracts_noisy_nik_labels_and_digit_substitutions(): void
    {
        $text = <<<'TEXT'
        PROVINSI JAWA TIMUR
        KOTA MALANG
        yk: 3573O164O587OOO5 aan
        Nama -ENGGA MEGA KRISTIYA
        Alamat ML TELUK PELABUHAN RATU77
        TEXT;

        $parsed = (new KtpOcrParser)->parse($text);

        $this->assertSame('3573016405870005', $parsed['nik']);
        $this->assertSame('ENGGA MEGA KRISTIYA', $parsed['name']);
    }

    public function test_it_extracts_biodata_style_document_fields(): void
    {
        $text = <<<'TEXT'
        BIODATA PENDUDUK WARGA NEGARA INDONESIA
        1. Nama Lengkap SLAMET
        2. Tempat Lahir : MALANG
        3. Tanggal Lahir :20-01-1980
        18. Alamat Sekarang ALAMAT: DK ARAN-ARAN
        RT : 044 RW: 011 DUSUN :
        DESA/KELURAHAN: SUMBEREJO
        KECAMATAN: PONCOKUSUMO
        KABUPATEN/KOTA :MALANG
        PROVINSI: JAWA TIMUR
        TEXT;

        $parsed = (new KtpOcrParser)->parse($text);

        $this->assertSame('SLAMET', $parsed['name']);
        $this->assertSame('DK ARAN-ARAN', $parsed['address']);
        $this->assertSame('044', $parsed['rt']);
        $this->assertSame('011', $parsed['rw']);
        $this->assertSame('SUMBEREJO', $parsed['village']);
        $this->assertSame('PONCOKUSUMO', $parsed['district']);
        $this->assertSame('MALANG', $parsed['city']);
        $this->assertSame('JAWA TIMUR', $parsed['province']);
    }

    public function test_it_keeps_valid_noisy_address_content_while_trimming_artifacts(): void
    {
        $text = <<<'TEXT'
        NIK : 3507072203970003
        Nama : JOHAR WAHYU
        Alamat : JL. TELUK PELABUHAN RATU NO.77 GG. UI
        TEXT;

        $parsed = (new KtpOcrParser)->parse($text);

        $this->assertSame('JL. TELUK PELABUHAN RATU NO.77 GG', $parsed['address']);
    }

    public function test_it_extracts_easyocr_label_value_line_pairs(): void
    {
        $text = <<<'TEXT'
        PROVINSI JAWA TIMUR
        KABUPATENMALANG
        3507070606000001
        NIK
        RAFIT CAHYADI
        Nama
        MALANG; 06-06-2000
        TempatTgl Lahir
        Alamat
        DUKUH ARAN-ARAN
        RTIRW
        034/009
        SUMBEREJO
        KeVDesa
        Kecamatan
        PONCOKUSUMO
        Agama
        ISLAM
        TEXT;

        $parsed = (new KtpOcrParser)->parse($text);

        $this->assertSame('3507070606000001', $parsed['nik']);
        $this->assertSame('RAFIT CAHYADI', $parsed['name']);
        $this->assertSame('DUKUH ARAN-ARAN', $parsed['address']);
        $this->assertSame('034', $parsed['rt']);
        $this->assertSame('009', $parsed['rw']);
        $this->assertSame('SUMBEREJO', $parsed['village']);
        $this->assertSame('PONCOKUSUMO', $parsed['district']);
        $this->assertSame('KABUPATEN MALANG', $parsed['city']);
        $this->assertSame('JAWA TIMUR', $parsed['province']);
    }
}
