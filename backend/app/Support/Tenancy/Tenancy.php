<?php

namespace App\Support\Tenancy;

use App\Enums\UserRole;
use App\Models\Group;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Auth;

/**
 * Holds the "current group" (restaurant) for the request.
 *
 * Resolution order:
 *  1. a group set explicitly (middleware, queued job, console command, tests);
 *  2. the authenticated user's group_id.
 *
 * Registered as a scoped singleton, so state never leaks between requests
 * (Octane) or queued jobs.
 */
class Tenancy
{
    private ?int $groupId = null;

    private int $bypassDepth = 0;

    public function set(Group|int|null $group): void
    {
        $this->groupId = $group instanceof Group ? $group->getKey() : $group;
    }

    public function forget(): void
    {
        $this->groupId = null;
    }

    public function id(): ?int
    {
        return $this->groupId ?? $this->user()?->group_id;
    }

    public function user(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    /**
     * Should GroupScope filter queries at all?
     * Unscoped: explicit bypass, master admins without a selected group,
     * and code running without any user (console, queue, seeders).
     */
    public function shouldScope(): bool
    {
        if ($this->bypassDepth > 0) {
            return false;
        }

        if ($this->groupId !== null) {
            return true;
        }

        $user = $this->user();

        return $user !== null && $user->role !== UserRole::MasterAdmin;
    }

    /**
     * Run a callback inside a specific group's context.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function runAs(Group|int $group, Closure $callback): mixed
    {
        $previous = $this->groupId;
        $this->set($group);

        try {
            return $callback();
        } finally {
            $this->groupId = $previous;
        }
    }

    /**
     * Run a callback with GroupScope disabled (cross-tenant admin reports, maintenance).
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function withoutScope(Closure $callback): mixed
    {
        $this->bypassDepth++;

        try {
            return $callback();
        } finally {
            $this->bypassDepth--;
        }
    }
}
