<?php

namespace App\Filament\Resources\Registrations\Tables;

use App\Models\Registration;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RegistrationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Pelanggan')->searchable()->sortable(),
                TextColumn::make('phone')->label('Telepon')->searchable(),
                TextColumn::make('area.name')->label('Area')->searchable(),
                TextColumn::make('package')->label('Paket')->searchable()->toggleable(),
                TextColumn::make('technician.name')->label('Teknisi')->searchable()->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Registration::STATUS_SUBMITTED => 'warning',
                        Registration::STATUS_APPROVED => 'success',
                        Registration::STATUS_NEEDS_REVISION => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => __('registration.status.'.$state)),
                TextColumn::make('created_at')->label('Dibuat')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        Registration::STATUS_DRAFT => 'Draf',
                        Registration::STATUS_SUBMITTED => 'Menunggu Tinjauan',
                        Registration::STATUS_NEEDS_REVISION => 'Perlu Revisi',
                        Registration::STATUS_APPROVED => 'Disetujui',
                        Registration::STATUS_CANCELLED => 'Dibatalkan',
                    ]),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Setujui')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Registration $record): bool => $record->status === Registration::STATUS_SUBMITTED)
                    ->action(function (Registration $record): void {
                        $record->update([
                            'status' => Registration::STATUS_APPROVED,
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                        ]);
                    }),
                Action::make('needs_revision')
                    ->label('Perlu Revisi')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Registration $record): bool => in_array($record->status, [Registration::STATUS_SUBMITTED, Registration::STATUS_APPROVED], true))
                    ->action(function (Registration $record): void {
                        $record->update([
                            'status' => Registration::STATUS_NEEDS_REVISION,
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                        ]);
                    }),
                EditAction::make()
                    ->label('Ubah'),
            ]);
    }
}
