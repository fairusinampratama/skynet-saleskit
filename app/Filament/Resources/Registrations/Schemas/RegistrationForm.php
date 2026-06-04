<?php

namespace App\Filament\Resources\Registrations\Schemas;

use App\Models\Registration;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class RegistrationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Keputusan Review')
                ->columns(2)
                ->schema([
                    Select::make('status')
                        ->label('Status')
                        ->options([
                            Registration::STATUS_DRAFT => 'Draf',
                            Registration::STATUS_SUBMITTED => 'Menunggu Tinjauan',
                            Registration::STATUS_NEEDS_REVISION => 'Perlu Revisi',
                            Registration::STATUS_APPROVED => 'Disetujui',
                            Registration::STATUS_CANCELLED => 'Dibatalkan',
                        ])
                        ->required(),
                    Textarea::make('admin_notes')
                        ->label('Catatan Admin')
                        ->rows(4)
                        ->columnSpanFull(),
                ]),
            Section::make('Data Layanan')
                ->columns(2)
                ->schema([
                    Select::make('area_id')
                        ->label('Area')
                        ->relationship('area', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Select::make('package')
                        ->label('Paket')
                        ->options(Registration::packageOptions())
                        ->required(),
                ]),
            Section::make('Ringkasan Pelanggan')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nama Pelanggan')
                        ->disabled()
                        ->dehydrated(false),
                    TextInput::make('nik')
                        ->label('NIK')
                        ->disabled()
                        ->dehydrated(false),
                    TextInput::make('phone')
                        ->label('Nomor Telepon')
                        ->disabled()
                        ->dehydrated(false),
                    Placeholder::make('technician_name')
                        ->label('Teknisi')
                        ->content(fn (?Registration $record): string => $record?->technician?->name ?? '-'),
                ]),
            Section::make('Alamat dan GPS')
                ->columns(2)
                ->schema([
                    Textarea::make('installation_full_address')
                        ->label('Alamat Instalasi')
                        ->disabled()
                        ->dehydrated(false)
                        ->columnSpanFull(),
                    Textarea::make('ktp_full_address')
                        ->label('Alamat KTP')
                        ->disabled()
                        ->dehydrated(false)
                        ->columnSpanFull(),
                    TextInput::make('latitude')
                        ->label('Geo Lat')
                        ->disabled()
                        ->dehydrated(false),
                    TextInput::make('longitude')
                        ->label('Geo Long')
                        ->disabled()
                        ->dehydrated(false),
                ]),
            Section::make('Bukti Lapangan')
                ->columns(2)
                ->schema([
                    Placeholder::make('ktp_document')
                        ->label('Foto KTP')
                        ->content(fn (?Registration $record): HtmlString => self::fileLink($record?->ktp_processed_file_path ?: $record?->ktp_original_file_path, 'Lihat KTP')),
                    Placeholder::make('location_evidence')
                        ->label('Foto Lokasi')
                        ->content(fn (?Registration $record): HtmlString => self::fileLink(
                            $record?->evidence()->where('evidence_type', 'location_photo')->latest()->value('file_path'),
                            'Lihat Foto Lokasi',
                        )),
                    Textarea::make('technician_notes')
                        ->label('Catatan Teknisi')
                        ->disabled()
                        ->dehydrated(false)
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
            Section::make('Debug / Integrasi')
                ->columns(2)
                ->collapsible()
                ->collapsed()
                ->schema([
                    TextInput::make('ktp_original_file_path')
                        ->label('KTP Original')
                        ->disabled()
                        ->dehydrated(false),
                    TextInput::make('ktp_processed_file_path')
                        ->label('KTP Processed')
                        ->disabled()
                        ->dehydrated(false),
                    Textarea::make('ktp_ocr_raw_text')
                        ->label('Teks OCR')
                        ->disabled()
                        ->dehydrated(false)
                        ->columnSpanFull(),
                    Placeholder::make('ebilling_customer_payload')
                        ->label('Mapping Customer')
                        ->content(fn (?Registration $record): string => $record
                            ? collect($record->toEbillingCustomerPayload())
                                ->map(fn ($value, string $key): string => $key.': '.(filled($value) ? (string) $value : '-'))
                                ->join("\n")
                            : '-')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    private static function fileLink(?string $path, string $label): HtmlString
    {
        if (blank($path)) {
            return new HtmlString('-');
        }

        $url = e(Storage::disk('public')->url($path));
        $label = e($label);

        return new HtmlString("<a href=\"{$url}\" target=\"_blank\" rel=\"noopener noreferrer\" class=\"font-semibold text-primary-600 underline\">{$label}</a>");
    }
}
