<?php

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PhotoController extends Controller
{
    public function store(Request $request, Asset $asset): RedirectResponse
    {
        $this->authorize('update', $asset);

        $request->validate([
            'photos'   => 'required|array',
            'photos.*' => 'image|max:10240', // 10 MB
        ]);

        $hasPrimary = $asset->photos()->where('is_primary', true)->exists();

        foreach ($request->file('photos') as $file) {
            $path = $file->store("assets/{$asset->id}/photos", 'public');

            $asset->photos()->create([
                'path'       => $path,
                'is_primary' => ! $hasPrimary,
                'sort_order' => $asset->photos()->count(),
            ]);

            $hasPrimary = true;
        }

        return back()->with('success', 'Photo(s) uploaded.');
    }

    public function destroy(Asset $asset, AssetPhoto $photo): RedirectResponse
    {
        $this->authorize('update', $asset);

        Storage::disk('public')->delete($photo->path);
        $wasPrimary = $photo->is_primary;
        $photo->delete();

        if ($wasPrimary) {
            $asset->photos()->orderBy('sort_order')->first()?->update(['is_primary' => true]);
        }

        return back()->with('success', 'Photo removed.');
    }

    public function setPrimary(Asset $asset, AssetPhoto $photo): RedirectResponse
    {
        $this->authorize('update', $asset);

        $asset->photos()->update(['is_primary' => false]);
        $photo->update(['is_primary' => true]);

        return back()->with('success', 'Primary photo updated.');
    }
}
