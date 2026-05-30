<?php

namespace App\Filament\Resources\Tasks\Widgets;

use App\Models\Task;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TaskOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $query = Task::query();
        if (auth()->user()->role === 'technician') {
            $query->where('assigned_to', auth()->id());
        }

        return [
            Stat::make('Total Tugas', (clone $query)->count()),
            Stat::make('Tugas Selesai', (clone $query)->where('status', 'completed')->count()),
            Stat::make('Tugas Menunggu', (clone $query)->where('status', 'waiting')->count()),
        ];
    }
}
