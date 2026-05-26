<?php

namespace Tests\Unit;

use App\Services\KtpOcrParser;
use PHPUnit\Framework\TestCase;

class KtpOcrParserTest extends TestCase
{
    public function test_it_extracts_common_ktp_fields_from_ocr_text(): void
    {
        $text = <<<TEXT
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

        $parsed = (new KtpOcrParser())->parse($text);

        $this->assertSame('3507182709770004', $parsed['nik']);
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
}
