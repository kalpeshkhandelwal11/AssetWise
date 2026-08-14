<?php

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetAttachment;
use App\Rules\ImageUnderSize;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    public function store(Request $request, Asset $asset): RedirectResponse
    {
        $this->authorize('update', $asset);

        $data = $request->validate([
            'type' => 'required|in:invoice,warranty_card,manual,agreement,photo',
            // Images capped at 2 MB (compressed client-side first); other files (PDFs) up to 20 MB.
            'file' => ['required', 'file', 'max:20480', new ImageUnderSize(2048)],
        ]);

        $file = $request->file('file');
        $path = $file->store("assets/{$asset->id}/attachments", 'public');

        $asset->attachments()->create([
            'type'          => $data['type'],
            'path'          => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime'          => $file->getClientMimeType(),
            'size'          => $file->getSize(),
        ]);

        return back()->with('success', 'Attachment uploaded.');
    }

    public function destroy(Asset $asset, AssetAttachment $attachment): RedirectResponse
    {
        $this->authorize('update', $asset);

        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();

        return back()->with('success', 'Attachment removed.');
    }
}
