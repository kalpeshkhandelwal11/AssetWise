<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\NotificationPreference;
use App\Support\NotificationCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request, NotificationCatalog $catalog): View
    {
        return view('profile.edit', [
            'user'    => $request->user(),
            'catalog' => $catalog,
            'preferences' => $request->user()->notificationPreferences()->pluck('email_enabled', 'type'),
        ]);
    }

    /**
     * Sparse by design (M12, matches notification_preferences' schema comment): only rows
     * that DIFFER from the catalog default are stored, so a type added to the catalog later
     * needs no backfill and shows the catalog default automatically.
     */
    public function updateNotifications(Request $request, NotificationCatalog $catalog): RedirectResponse
    {
        $user = $request->user();
        $checked = $request->input('email_enabled', []);

        foreach ($catalog->all() as $type => $entry) {
            if (! in_array('mail', $entry['channels'] ?? [], true)) {
                continue;
            }

            $default = true;
            $wants = in_array($type, $checked, true);

            if ($wants === $default) {
                NotificationPreference::where('user_id', $user->id)->where('type', $type)->delete();
            } else {
                NotificationPreference::updateOrCreate(
                    ['user_id' => $user->id, 'type' => $type],
                    ['email_enabled' => $wants],
                );
            }
        }

        return Redirect::route('profile.edit')->with('status', 'notifications-updated');
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
