<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Services\TagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScanController extends Controller
{
    public function __construct(private readonly TagService $tags)
    {
    }

    /**
     * GET /scan/{tag_number} — web-session authenticated, matching every other route in
     * this app. Resolves by status: assigned -> straight to the asset detail page,
     * available -> an assign prompt, inactive -> a retired message.
     */
    public function resolve(Request $request, string $tag_number): View|RedirectResponse
    {
        $this->authorize('tags.view');

        $result = $this->tags->resolveScan($tag_number);
        $method = $request->query('method') === 'camera' ? 'camera' : 'manual';
        $this->tags->logScan($result->tag, $result->asset, $request->user(), $method);

        if ($result->status === 'assigned' && $result->asset) {
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
