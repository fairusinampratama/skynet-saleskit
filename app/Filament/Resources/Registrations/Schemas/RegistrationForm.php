<?php

namespace App\Filament\Resources\Registrations\Schemas;

use App\Models\Registration;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RegistrationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Tinjauan')
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
                    Select::make('area_id')
                        ->label('Area')
                        ->relationship('area', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Textarea::make('admin_notes')->label('Catatan Admin')->columnSpanFull(),
                ]),
            Section::make('Layanan')
                ->columns(2)
                ->schema([
                    Select::make('package')
                        ->label('Paket')
                        ->options(Registration::packageOptions())
                        ->required(),
                ]),
            Section::make('Pelanggan')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->label('Nama Pelanggan')->required(),
                    TextInput::make('nik')->required(),
                    TextInput::make('phone')->label('Nomor Telepon')->required(),
                ]),
            Section::make('Data E-Billing')
                ->columns(2)
                ->schema([
                    Textarea::make('installation_full_address')
                        ->label('Alamat Customer / Instalasi')
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
                    Placeholder::make('ebilling_customer_payload')
                        ->label('Mapping Customer')
                        ->content(fn (?Registration $record): string => $record
                            ? collect($record->toEbillingCustomerPayload())
                                ->map(fn ($value, string $key): string => $key.': '.(filled($value) ? (string) $value : '-'))
                                ->join("\n")
                            : '-')
                        ->columnSpanFull(),
                ]),
            Section::make('Bukti Lapangan')
                ->columns(2)
                ->schema([
                    TextInput::make('ktp_processed_file_path')
                        ->label('KTP OCR / Processed')
                        ->disabled()
                        ->dehydrated(false),
                    TextInput::make('ktp_original_file_path')
                        ->label('KTP Original')
                        ->disabled()
                        ->dehydrated(false),
                    Textarea::make('ktp_ocr_raw_text')
                        ->label('Teks OCR')
                        ->disabled()
                        ->dehydrated(false)
                        ->columnSpanFull(),
                    Placeholder::make('location_evidence')
                        ->label('Foto Lokasi')
                        ->content(fn (?Registration $record): string => $record?->evidence()->where('evidence_type', 'location_photo')->latest()->value('file_path') ?? '-')
                        ->columnSpanFull(),
                    Textarea::make('technician_notes')
                        ->label('Catatan Teknisi')
                        ->disabled()
                        ->dehydrated(false)
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
