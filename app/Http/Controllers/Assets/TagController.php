<?php

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Tag;
use App\Services\TagService;
use App\Services\WorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TagController extends Controller
{
    public function __construct(
        private readonly TagService $tags,
        private readonly WorkflowService $workflows,
    ) {
    }

    /** Assign an available tag to an asset — picker (tag_id) or scan-to-assign (tag_number). */
    public function store(Request $request, Asset $asset): RedirectResponse
    {
        $this->authorize('tags.assign');

        $data = $request->validate([
            'tag_id'     => 'nullable|exists:tags,id',
            'tag_number' => 'nullable|string',
        ]);

        $tag = isset($data['tag_id'])
            ? Tag::find($data['tag_id'])
            : Tag::where('tag_number', $data['tag_number'] ?? null)->first();

        if (! $tag) {
            throw ValidationException::withMessages(['tag' => 'Tag not found.']);
        }

        $this->tags->assignToAsset($tag, $asset, $request->user());

        return redirect()->route('assets.show', $asset)->with('success', 'Tag assigned.');
    }

    public function replaceForm(Asset $asset): View
    {
        $this->authorize('tags.replace');

        return view('modules.assets.tags.replace', [
            'asset'         => $asset,
            'availableTags' => Tag::where('status', 'available')->orderBy('tag_number')->get(),
        ]);
    }

    public function submitReplacement(Request $request, Asset $asset): RedirectResponse
    {
        $this->authorize('tags.replace');

        $data = $request->validate([
            'new_tag_id' => 'required|exists:tags,id',
            'reason'     => 'required|string|max:1000',
        ]);

        $newTag = Tag::findOrFail($data['new_tag_id']);
        $actor = $request->user();

        $replacement = $this->tags->requestReplacement($asset, $newTag, $data['reason'], $actor);
        $approvalRequest = $this->workflows->submit($replacement, 'tag_replacement', $actor);
        $replacement->update(['approval_request_id' => $approvalRequest->id]);

        return redirect()->route('assets.show', $asset)->with('success', 'Tag replacement submitted for approval.');
    }
}
