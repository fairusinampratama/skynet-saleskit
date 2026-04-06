<?php

namespace App\Filament\Resources\Tasks\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TaskOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $query = \App\Models\Task::query();
        if (auth()->user()->role === 'technician') {
            $query->where('assigned_to', auth()->id());
        }

        return [
            Stat::make('Total Tasks', (clone $query)->count()),
            Stat::make('Completed Tasks', (clone $query)->where('status', 'completed')->count()),
            Stat::make('Waiting Tasks', (clone $query)->where('status', 'waiting')->count()),
        ];
    }
}
