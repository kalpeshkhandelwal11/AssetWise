<?php

namespace App\Http\Controllers;

use App\Support\NotificationCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Top-level, not module-namespaced (M12) — this is a per-user resource, not an admin
 * screen, so it's scoped by ownership (auth()->user()->notifications()) rather than by an
 * RBAC permission. No new permission is seeded for it.
 */
class NotificationController extends Controller
{
    public function index(Request $request, NotificationCatalog $catalog): View
    {
        $notifications = $request->user()->notifications()
            ->latest()
            ->paginate(20);

        return view('notifications.index', [
            'notifications' => $notifications,
            'catalog'       => $catalog,
        ]);
    }

    /**
     * Marks the notification read, then redirects to the record it points at — one click
     * both clears the badge and opens the record. Scoped to the authenticated user's own
     * notifications() query rather than route-model-bound, so one user can never mark (or
     * even probe the existence of) another user's notification by guessing its uuid.
     */
    public function read(Request $request, NotificationCatalog $catalog, string $notification): RedirectResponse
    {
        $record = $request->user()->notifications()->findOrFail($notification);

        if (! $record->read_at) {
            $record->markAsRead();
        }

        $link = $catalog->link($record->type, $record->data);

        return $link ? redirect($link) : redirect()->route('notifications.index');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }
}
