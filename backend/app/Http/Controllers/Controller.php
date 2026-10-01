<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Timezone used for "today / overdue / 7 days": the restaurant's own,
     * so every staff member sees the same buckets regardless of their laptop.
     */
    protected function timezone(Request $request): string
    {
        return $request->user()?->group?->timezone ?? config('app.timezone');
    }
}
