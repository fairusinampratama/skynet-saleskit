<?php

namespace App\Filament\Resources\Areas\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AreaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Area')
                ->columns(2)
                ->schema([
                    TextInput::make('code')->label('Kode')->required()->unique(ignoreRecord: true)->maxLength(255),
                    TextInput::make('name')->label('Nama')->required()->maxLength(255),
                    Toggle::make('active')->label('Aktif')->default(true),
                ]),
            Section::make('Lokasi Administratif')
                ->columns(2)
                ->schema([
                    TextInput::make('province')->label('Provinsi')->maxLength(255),
                    TextInput::make('city')->label('Kota / Kabupaten')->maxLength(255),
                    TextInput::make('district')->label('Kecamatan')->maxLength(255),
                    TextInput::make('village')->label('Desa / Kelurahan')->maxLength(255),
                    Textarea::make('coverage_notes')->label('Catatan Cakupan')->columnSpanFull(),
                ]),
        ]);
    }
}
