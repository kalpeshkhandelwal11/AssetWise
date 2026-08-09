<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Models\TagBatch;
use App\Services\Tags\TagLabelRenderer;
use App\Services\Tags\TagLabelWordExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TagPrintController extends Controller
{
    public function __construct(
        private readonly TagLabelRenderer $renderer,
        private readonly TagLabelWordExport $wordExport,
    ) {
    }

    public function pdf(Request $request)
    {
        $this->authorize('tags.print');

        $tags = $this->resolveTags($request);
        $rendered = $this->renderer->render($tags);

        return Pdf::loadView('admin.tags.print-pdf', ['tags' => $rendered])
            ->download('tags-' . now()->format('Ymd-His') . '.pdf');
    }

    public function word(Request $request): StreamedResponse
    {
        $this->authorize('tags.print');

        $tags = $this->resolveTags($request);

        return $this->wordExport->download($tags, 'tags-' . now()->format('Ymd-His') . '.docx');
    }

    private function resolveTags(Request $request)
    {
        $data = $request->validate([
            'batch'    => 'nullable|exists:tag_batches,id',
            'tag_ids'  => 'nullable|array',
            'tag_ids.*' => 'exists:tags,id',
        ]);

        if (! empty($data['tag_ids'])) {
            return Tag::whereIn('id', $data['tag_ids'])->orderBy('tag_number')->get();
        }

        if (! empty($data['batch'])) {
            return TagBatch::findOrFail($data['batch'])->tags()->orderBy('tag_number')->get();
        }

        return Tag::where('status', 'available')->orderBy('tag_number')->get();
    }
}
