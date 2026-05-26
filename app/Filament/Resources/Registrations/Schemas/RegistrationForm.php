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
            Section::make('Review')
                ->columns(2)
                ->schema([
                    Select::make('status')
                        ->options([
                            Registration::STATUS_DRAFT => 'Draft',
                            Registration::STATUS_SUBMITTED => 'Submitted',
                            Registration::STATUS_NEEDS_REVISION => 'Needs Revision',
                            Registration::STATUS_APPROVED => 'Approved',
                            Registration::STATUS_SYNCING => 'Syncing',
                            Registration::STATUS_SYNCED => 'Synced',
                            Registration::STATUS_SYNC_FAILED => 'Sync Failed',
                            Registration::STATUS_CANCELLED => 'Cancelled',
                        ])
                        ->required(),
                    Select::make('area_id')
                        ->relationship('area', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Textarea::make('admin_notes')->columnSpanFull(),
                ]),
            Section::make('Customer')
                ->relationship('customer')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->required(),
                    TextInput::make('nik')->required(),
                    TextInput::make('phone')->required(),
                    TextInput::make('email')->email(),
                ]),
        ]);
    }
}
