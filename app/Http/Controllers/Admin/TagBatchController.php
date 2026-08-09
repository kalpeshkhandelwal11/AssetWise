<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Tag;
use App\Services\TagService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TagBatchController extends Controller
{
    public function __construct(private TagService $tags)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('tags.view');

        $query = Tag::with('batch')->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where('tag_number', 'like', '%' . $request->search . '%');
        }

        return view('admin.tags.index', [
            'tags' => $query->paginate(30)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('tags.generate');

        return view('admin.tags.batches.create', [
            'codeType' => Setting::get('tag_code_type', 'qr'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('tags.generate');

        $data = $request->validate([
            'quantity' => 'required|integer|min:1|max:1000',
        ]);

        $batch = $this->tags->generateBatch((int) $data['quantity'], $request->user());

        return redirect()->route('admin.tags.index')
            ->with('success', "Generated {$batch->quantity} {$batch->code_type} tags.");
    }
}
