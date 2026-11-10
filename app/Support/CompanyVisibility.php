<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Single source of truth for "which companies may the current actor see?".
 *
 * ids() returns null when the actor is unrestricted (no authenticated user — console,
 * scheduler, seeders — or a user with all_companies / companies.view_all), otherwise the
 * list of visible company ids (possibly empty, meaning "nothing").
 *
 * Queued jobs have no session, so a job that produces user-facing data (exports, imports)
 * must wrap its work in actingAs($user, fn) or it would run unrestricted and leak every
 * company's rows into the file.
 */
final class CompanyVisibility
{
    private static bool $overridden = false;

    private static ?User $actor = null;

    /** Run $callback with visibility evaluated for $user instead of the session user. */
    public static function actingAs(?User $user, callable $callback): mixed
    {
        $prevOverridden = self::$overridden;
        $prevActor = self::$actor;

        self::$overridden = true;
        self::$actor = $user;

        try {
            return $callback();
        } finally {
            self::$overridden = $prevOverridden;
            self::$actor = $prevActor;
        }
    }

    /** Run $callback with no company restriction (system-level work inside a web request). */
    public static function unrestricted(callable $callback): mixed
    {
        return self::actingAs(null, $callback);
    }

    public static function actor(): ?User
    {
        if (self::$overridden) {
            return self::$actor;
        }

        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    /** @return int[]|null null = unrestricted */
    public static function ids(): ?array
    {
        return self::actor()?->visibleCompanyIds();
    }

    public static function isRestricted(): bool
    {
        return self::ids() !== null;
    }

    public static function canSee(?int $companyId): bool
    {
        $ids = self::ids();

        return $ids === null || ($companyId !== null && in_array($companyId, $ids, true));
    }
}
