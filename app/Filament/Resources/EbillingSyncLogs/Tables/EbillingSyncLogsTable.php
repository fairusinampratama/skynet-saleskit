<?php

namespace App\Filament\Resources\EbillingSyncLogs\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EbillingSyncLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('registration.customer.name')->label('Customer')->searchable(),
                TextColumn::make('syncedBy.name')->label('Synced By')->searchable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('error_message')->limit(50)->toggleable(),
                TextColumn::make('started_at')->dateTime()->sortable(),
                TextColumn::make('finished_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'success' => 'Success',
                        'failed' => 'Failed',
                    ]),
            ]);
    }
}
