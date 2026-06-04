<?php

namespace App\Filament\Resources\Registrations\Schemas;

use App\Models\Registration;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class RegistrationForm
{
    private static function requiredWhenNotDraft(): \Closure
    {
        return fn (Get $get): bool => $get('status') !== Registration::STATUS_DRAFT;
    }

    private static function photoUpload(string $name, string $label, string $directory): FileUpload
    {
        return FileUpload::make($name)
            ->label($label)
            ->disk('public')
            ->directory($directory)
            ->visibility('public')
            ->image()
            ->imagePreviewHeight('260')
            ->itemPanelAspectRatio('1.58')
            ->previewable()
            ->openable()
            ->downloadable()
            ->maxSize(20480);
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Status')
                ->columns(2)
                ->schema([
                    Select::make('status')
                        ->label('Status')
                        ->options([
                            Registration::STATUS_DRAFT => 'Draf',
                            Registration::STATUS_SUBMITTED => 'Menunggu Tinjauan',
                            Registration::STATUS_APPROVED => 'Disetujui',
                        ])
                        ->default(Registration::STATUS_DRAFT)
                        ->required(),
                    Select::make('registered_by')
                        ->label('Teknisi')
                        ->relationship('technician', 'name')
                        ->searchable()
                        ->preload()
                        ->default(fn (): ?int => auth()->id())
                        ->required(),
                ]),
            Section::make('Data Pelanggan')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nama Pelanggan')
                        ->maxLength(255)
                        ->required(self::requiredWhenNotDraft()),
                    TextInput::make('nik')
                        ->label('NIK')
                        ->minLength(16)
                        ->maxLength(16)
                        ->numeric()
                        ->required(self::requiredWhenNotDraft()),
                    TextInput::make('phone')
                        ->label('Nomor Telepon')
                        ->maxLength(30)
                        ->required(self::requiredWhenNotDraft()),
                    Select::make('package')
                        ->label('Paket')
                        ->options(Registration::packageOptions())
                        ->required(self::requiredWhenNotDraft()),
                    Select::make('area_id')
                        ->label('Area')
                        ->relationship('area', 'name')
                        ->searchable()
                        ->preload()
                        ->required(self::requiredWhenNotDraft()),
                ]),
            Section::make('Alamat dan GPS')
                ->columns(2)
                ->schema([
                    Textarea::make('ktp_full_address')
                        ->label('Alamat KTP')
                        ->maxLength(2000)
                        ->columnSpanFull(),
                    Textarea::make('installation_full_address')
                        ->label('Alamat Instalasi')
                        ->maxLength(2000)
                        ->required(self::requiredWhenNotDraft())
                        ->columnSpanFull(),
                    TextInput::make('latitude')
                        ->label('Latitude')
                        ->numeric()
                        ->required(self::requiredWhenNotDraft()),
                    TextInput::make('longitude')
                        ->label('Longitude')
                        ->numeric()
                        ->required(self::requiredWhenNotDraft()),
                ]),
            Section::make('Foto dan Catatan')
                ->columns(2)
                ->schema([
                    self::photoUpload('ktp_photo_path', 'Foto KTP', 'ktp')
                        ->helperText('Pratinjau dapat dibuka ukuran penuh untuk memeriksa NIK dan nama.')
                        ->required(self::requiredWhenNotDraft()),
                    self::photoUpload('location_photo_path', 'Foto Rumah / Lokasi', 'registration-location')
                        ->helperText('Opsional. Simpan jika teknisi menyertakan bukti lokasi.'),
                    Textarea::make('technician_notes')
                        ->label('Catatan Teknisi')
                        ->maxLength(2000)
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
