<?php

namespace App\Filament\Resources\Registrations\Schemas;

use App\Models\Registration;
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
                    TextInput::make('email')->label('Alamat Email')->email(),
                ]),
        ]);
    }
}
