<?php

namespace App\Filament\Resources\Areas\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AreaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama')->required()->maxLength(255),
        ]);
    }
}
