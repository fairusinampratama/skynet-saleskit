<?php

namespace App\Filament\Resources\EbillingSyncLogs;

use App\Filament\Resources\EbillingSyncLogs\Pages\ListEbillingSyncLogs;
use App\Filament\Resources\EbillingSyncLogs\Tables\EbillingSyncLogsTable;
use App\Models\EbillingSyncLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class EbillingSyncLogResource extends Resource
{
    protected static ?string $model = EbillingSyncLog::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';

    protected static string|UnitEnum|null $navigationGroup = 'Registration';

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return EbillingSyncLogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEbillingSyncLogs::route('/'),
        ];
    }
}
