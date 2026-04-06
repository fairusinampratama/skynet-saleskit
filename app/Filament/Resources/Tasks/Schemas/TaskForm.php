<?php

namespace App\Filament\Resources\Tasks\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class TaskForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Task Assignment')
                    ->description('Link this task to a customer and technician')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->columns(2)
                    ->schema([
                        Select::make('customer_id')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->required()
                            ->disabled(fn () => auth()->user()->role === 'technician'),
                        Select::make('assigned_to')
                            ->relationship('technician', 'name', fn (Builder $query) => $query->where('role', 'technician'))
                            ->label('Technician')
                            ->required()
                            ->disabled(fn () => auth()->user()->role === 'technician'),
                    ]),

                Section::make('Task Details')
                    ->icon('heroicon-o-information-circle')
                    ->columns(2)
                    ->schema([
                        Select::make('task_type')
                            ->options([
                                'installation' => 'Installation',
                                'disconnection' => 'Disconnection',
                            ])
                            ->required()
                            ->disabled(fn () => auth()->user()->role === 'technician'),
                        Select::make('status')
                            ->options([
                                'waiting' => 'Waiting',
                                'progress' => 'In Progress',
                                'completed' => 'Completed',
                                'failed' => 'Failed',
                            ])
                            ->required()
                            ->default('waiting'),
                    ]),

                Section::make('Execution & Evidence')
                    ->icon('heroicon-o-camera')
                    ->schema([
                        Textarea::make('technician_notes')
                            ->label('Technician Notes')
                            ->rows(3)
                            ->columnSpanFull(),
                        FileUpload::make('photo_evidence')
                            ->label('Photo Evidence')
                            ->image()
                            ->imageEditor()
                            ->disk('public')
                            ->maxSize(2048)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
