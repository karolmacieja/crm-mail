<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Shared "window" filter for tasks and reminders:
 * overdue | today | upcoming (7 days) | open | done | all.
 */
trait FiltersByDueWindow
{
    public const WINDOWS = ['overdue', 'today', 'upcoming', 'open', 'done', 'completed', 'all'];

    protected function applyWindow(Builder $query, string $window, string $timezone): Builder
    {
        $model = $query->getModel();

        return match ($window) {
            'overdue' => $query->overdue(),
            'today' => $query->dueToday($timezone),
            'upcoming' => $query->upcoming($timezone),
            'open' => $query->pending(),
            'done', 'completed' => $query->where($model->qualifyColumn($model::DONE_COLUMN), true),
            default => $query,
        };
    }
}
