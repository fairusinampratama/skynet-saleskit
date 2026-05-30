<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Akun Pengguna')
                    ->description('Kelola kredensial masuk dan peran sistem')
                    ->icon('heroicon-o-user-circle')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('username')
                            ->label('Nama Pengguna')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Alamat Email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Select::make('role')
                            ->label('Peran')
                            ->options([
                                'admin' => 'Administrator',
                                'technician' => 'Teknisi',
                            ])
                            ->required()
                            ->default('technician'),
                        TextInput::make('password')
                            ->label('Kata Sandi')
                            ->password()
                            ->dehydrated(fn ($state) => filled($state))
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->required(fn (string $context): bool => $context === 'create')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
