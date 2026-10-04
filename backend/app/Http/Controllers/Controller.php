<?php

namespace App\Http\Controllers;

use App\Models\Concerns\SharedThroughContact;
use Illuminate\Database\Eloquent\Model;
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

    /**
     * Tasks, reminders and reservations follow their contact's sharing:
     * hidden ones are a 404; visible but someone else's are read-only (403).
     *
     * @param  Model&SharedThroughContact  $item
     */
    protected function authorizeItem(Request $request, Model $item, bool $view = false): void
    {
        $user = $request->user();
        abort_unless($item->isVisibleTo($user), 404);
        abort_unless($view || $item->canBeChangedBy($user), 403, __('crm.sharing.read_only'));
    }
}
