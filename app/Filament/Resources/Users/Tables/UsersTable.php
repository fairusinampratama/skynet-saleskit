<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('username')
                    ->label('Nama Pengguna')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('role')
                    ->label('Peran')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'admin' => 'Administrator',
                        'technician' => 'Teknisi',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'admin' => 'danger',
                        'technician' => 'info',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Alamat Email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->options([
                        'admin' => 'Administrator',
                        'technician' => 'Teknisi',
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Ubah'),
                DeleteAction::make()
                    ->label('Hapus'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Hapus terpilih'),
                ])->label('Aksi massal'),
            ]);
    }
}
