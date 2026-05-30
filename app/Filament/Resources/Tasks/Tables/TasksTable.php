<?php

namespace App\Filament\Resources\Tasks\Tables;

use App\Models\Task;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TasksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('registration.name')
                    ->label('Nama Pelanggan')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('task_type')
                    ->label('Jenis Tugas')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'installation' => 'Instalasi',
                        'disconnection' => 'Pemutusan',
                        default => $state,
                    })
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'waiting' => 'Menunggu',
                        'progress' => 'Diproses',
                        'completed' => 'Selesai',
                        'failed' => 'Gagal',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'waiting' => 'gray',
                        'progress' => 'warning',
                        'completed' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('technician.name')
                    ->label('Teknisi')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'waiting' => 'Menunggu',
                        'progress' => 'Diproses',
                        'completed' => 'Selesai',
                        'failed' => 'Gagal',
                    ]),
                SelectFilter::make('assigned_to')
                    ->relationship('technician', 'name', fn ($query) => $query->where('role', 'technician'))
                    ->label('Teknisi'),
            ])
            ->recordActions([
                Action::make('call')
                    ->label('Telepon')
                    ->icon('heroicon-o-phone')
                    ->color('success')
                    ->url(fn (Task $record): string => 'tel:'.($record->registration->phone ?? ''))
                    ->visible(fn (): bool => auth()->user()->role === 'technician'),
                EditAction::make()
                    ->label('Ubah'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Hapus terpilih'),
                ])->label('Aksi massal'),
            ]);
    }
}
