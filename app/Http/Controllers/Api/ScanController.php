<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Services\AuditService;
use App\Services\TagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScanController extends Controller
{
    public function __construct(
        private readonly TagService $tags,
        private readonly AuditService $audits,
    ) {
    }

    /**
     * GET /scan — M15's camera scan screen. Before this, nothing in the UI could start a
     * scan: /scan/{tag_number} only resolved when a tag number was already in the URL, i.e.
     * from a label scanned with the phone's native camera app.
     */
    public function index(): View
    {
        $this->authorize('tags.view');

        return view('scan.index');
    }

    /**
     * POST /scan — manual tag entry from <x-qr-scanner>. Exists because scan.resolve takes
     * the tag in the path, which a plain HTML form cannot build; this keeps the fallback
     * working with no JavaScript at all.
     */
    public function lookup(Request $request): RedirectResponse
    {
        $this->authorize('tags.view');

        $data = $request->validate([
            'tag_number' => 'required|string|max:100',
        ]);

        return redirect()->route('scan.resolve', ['tag_number' => trim($data['tag_number'])]);
    }

    /**
     * GET /scan/{tag_number} — web-session authenticated, matching every other route in
     * this app. Resolves by status: assigned -> straight to the asset detail page (or, for
     * an auditor with a pending audit item on this asset, into verification instead —
     * M10's consumer of this contract), available -> an assign prompt, inactive -> a
     * retired message.
     */
    public function resolve(Request $request, string $tag_number): View|RedirectResponse
    {
        $this->authorize('tags.view');

        $result = $this->tags->resolveScan($tag_number);
        $method = $request->query('method') === 'camera' ? 'camera' : 'manual';
        $this->tags->logScan($result->tag, $result->asset, $request->user(), $method);

        if ($result->status === 'assigned' && $result->asset) {
            $item = $request->user()->can('audit.verify')
                ? $this->audits->openItemFor($result->asset, $request->user())
                : null;

            if ($item) {
                return redirect()->route('audits.verify', ['campaign' => $item->campaign_id, 'item' => $item->id])
                    ->with('success', 'Tag scanned: ' . $result->tag->tag_number);
            }

            return redirect()->route('assets.show', $result->asset)->with('success', 'Tag scanned: ' . $result->tag->tag_number);
        }

        return view('scan.result', [
            'result'            => $result,
            'assignableAssets'  => $result->status === 'available' && $request->user()->can('tags.assign')
                ? Asset::whereDoesntHave('tagAssignments', fn ($q) => $q->where('status', 'active'))
                    ->orderBy('name')->limit(200)->get()
                : collect(),
        ]);
    }

    /** POST /api/scan — mobile scan logging (Alpine.js / html5-qrcode client). */
    public function log(Request $request): JsonResponse
    {
        $this->authorize('tags.view');

        $data = $request->validate([
            'tag_number' => 'required|string',
            'method'     => 'required|in:camera,manual',
        ]);

        $result = $this->tags->resolveScan($data['tag_number']);
        $this->tags->logScan($result->tag, $result->asset, $request->user(), $data['method']);

        return response()->json([
            'status'     => $result->status,
            'tag_number' => $result->tag->tag_number,
            'asset'      => $result->asset ? ['id' => $result->asset->id, 'name' => $result->asset->name] : null,
            'url'        => $result->asset ? route('assets.show', $result->asset) : null,
        ]);
    }
}
