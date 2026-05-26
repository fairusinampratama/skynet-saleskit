<?php

namespace App\Filament\Resources\EbillingSyncLogs\Pages;

use App\Filament\Resources\EbillingSyncLogs\EbillingSyncLogResource;
use Filament\Resources\Pages\ListRecords;

class ListEbillingSyncLogs extends ListRecords
{
    protected static string $resource = EbillingSyncLogResource::class;
}
