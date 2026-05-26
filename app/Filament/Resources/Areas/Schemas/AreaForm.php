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
                    TextInput::make('code')->required()->unique(ignoreRecord: true)->maxLength(255),
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('ebilling_area_code')
                        ->label('eBilling Area Code')
                        ->required()
                        ->maxLength(255),
                    Toggle::make('active')->default(true),
                ]),
            Section::make('Administrative Location')
                ->columns(2)
                ->schema([
                    TextInput::make('province')->maxLength(255),
                    TextInput::make('city')->label('City / Regency')->maxLength(255),
                    TextInput::make('district')->maxLength(255),
                    TextInput::make('village')->maxLength(255),
                    Textarea::make('coverage_notes')->columnSpanFull(),
                ]),
        ]);
    }
}
