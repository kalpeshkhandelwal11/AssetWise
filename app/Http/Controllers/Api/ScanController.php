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
        $this->authorize('scan.use');

        return view('scan.index');
    }

    /**
     * POST /scan — manual tag entry from <x-qr-scanner>. Exists because scan.resolve takes
     * the tag in the path, which a plain HTML form cannot build; this keeps the fallback
     * working with no JavaScript at all.
     */
    public function lookup(Request $request): RedirectResponse
    {
        $this->authorize('scan.use');

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
        $this->authorize('scan.use');

        $result = $this->tags->resolveScan($tag_number);
        $method = $request->query('method') === 'camera' ? 'camera' : 'manual';
        $this->tags->logScan($result->tag, $result->asset, $request->user(), $method);

        // Phase 2 — an asset arriving at its destination: route whoever may receive it straight
        // to the receipt form. Checked before company isolation, because the receiver of an
        // inter-company transfer cannot see the asset until it is received.
        if ($result->status === 'assigned' && ($receipt = $this->receiptRedirect($request, $result->tag))) {
            return $receipt;
        }

        // Company isolation: an assigned tag whose asset resolved to null belongs to a
        // company the scanner cannot see. Say nothing about the asset.
        if ($result->status === 'assigned' && ! $result->asset) {
            abort(404, 'This tag is not linked to an asset you have access to.');
        }

        // Location managers may scan to receive without holding tags.view — nothing else here.
        if (! $request->user()->can('tags.view')) {
            return redirect()->route('movements.incoming')->with('error', 'Nothing awaiting your receipt for tag '.$result->tag->tag_number.'.');
        }

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

    private function receiptRedirect(Request $request, \App\Models\Tag $tag): ?RedirectResponse
    {
        $assetId = $tag->activeAssignment?->asset_id;

        if (! $assetId) {
            return null;
        }

        $movement = \App\Support\CompanyVisibility::unrestricted(fn () => \App\Models\AssetMovement::query()
            ->where('asset_id', $assetId)
            ->where('receipt_status', 'in_transit')
            ->with('toLocation.manager')
            ->first());

        if (! $movement || ! app(\App\Services\MovementService::class)->canReceive($request->user(), $movement)) {
            return null;
        }

        return redirect()
            ->route($movement->batch_id ? 'movements.batches.receive' : 'movements.receive.form', $movement->batch_id ?: $movement)
            ->with('success', 'Tag scanned: '.$tag->tag_number.' — confirm receipt.');
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
