<?php

namespace App\Filament\Resources\Registrations\Tables;

use App\Jobs\SyncRegistrationToEbilling;
use App\Models\Registration;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RegistrationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer.name')->label('Customer')->searchable()->sortable(),
                TextColumn::make('customer.phone')->label('Phone')->searchable(),
                TextColumn::make('area.name')->label('Area')->searchable(),
                TextColumn::make('technician.name')->label('Technician')->searchable()->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Registration::STATUS_SUBMITTED => 'warning',
                        Registration::STATUS_APPROVED, Registration::STATUS_SYNCED => 'success',
                        Registration::STATUS_NEEDS_REVISION, Registration::STATUS_SYNC_FAILED => 'danger',
                        Registration::STATUS_SYNCING => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', $state)),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        Registration::STATUS_DRAFT => 'Draft',
                        Registration::STATUS_SUBMITTED => 'Submitted',
                        Registration::STATUS_NEEDS_REVISION => 'Needs Revision',
                        Registration::STATUS_APPROVED => 'Approved',
                        Registration::STATUS_SYNC_FAILED => 'Sync Failed',
                        Registration::STATUS_SYNCED => 'Synced',
                    ]),
            ])
            ->recordActions([
                Action::make('approve')
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
                    ->label('Needs Revision')
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
                Action::make('send_to_ebilling')
                    ->label('Send to eBilling')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (Registration $record): bool => in_array($record->status, [Registration::STATUS_APPROVED, Registration::STATUS_SYNC_FAILED], true))
                    ->action(function (Registration $record): void {
                        $record->loadMissing(['area', 'customer.installationAddress', 'customer.ktpDocument', 'evidence']);

                        if (! $record->area?->active || blank($record->area?->ebilling_area_code)) {
                            Notification::make()->danger()->title('Area needs an active eBilling code before sync.')->send();

                            return;
                        }

                        if (blank($record->customer->nik) || blank($record->customer->phone) || ! $record->customer->installationAddress || ! $record->customer->ktpDocument || $record->evidence->isEmpty()) {
                            Notification::make()->danger()->title('Registration is missing required customer, KTP, address, or evidence data.')->send();

                            return;
                        }

                        SyncRegistrationToEbilling::dispatch($record->id, auth()->id());
                        Notification::make()->success()->title('eBilling sync queued.')->send();
                    }),
                EditAction::make(),
            ]);
    }
}
