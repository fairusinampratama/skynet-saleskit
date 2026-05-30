<?php

namespace App\Filament\Resources\Tasks\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class TaskForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Penugasan')
                    ->description('Hubungkan tugas ke registrasi dan teknisi')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->columns(2)
                    ->schema([
                        Select::make('registration_id')
                            ->label('Registrasi')
                            ->relationship('registration', 'name')
                            ->searchable()
                            ->required()
                            ->disabled(fn () => auth()->user()->role === 'technician'),
                        Select::make('assigned_to')
                            ->relationship('technician', 'name', fn (Builder $query) => $query->where('role', 'technician'))
                            ->label('Teknisi')
                            ->required()
                            ->disabled(fn () => auth()->user()->role === 'technician'),
                    ]),

                Section::make('Detail Tugas')
                    ->icon('heroicon-o-information-circle')
                    ->columns(2)
                    ->schema([
                        Select::make('task_type')
                            ->label('Jenis Tugas')
                            ->options([
                                'installation' => 'Instalasi',
                                'disconnection' => 'Pemutusan',
                            ])
                            ->required()
                            ->disabled(fn () => auth()->user()->role === 'technician'),
                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'waiting' => 'Menunggu',
                                'progress' => 'Diproses',
                                'completed' => 'Selesai',
                                'failed' => 'Gagal',
                            ])
                            ->required()
                            ->default('waiting'),
                    ]),

                Section::make('Pelaksanaan dan Bukti')
                    ->icon('heroicon-o-camera')
                    ->schema([
                        Textarea::make('technician_notes')
                            ->label('Catatan Teknisi')
                            ->rows(3)
                            ->columnSpanFull(),
                        FileUpload::make('photo_evidence')
                            ->label('Foto Bukti')
                            ->image()
                            ->imageEditor()
                            ->disk('public')
                            ->maxSize(2048)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
