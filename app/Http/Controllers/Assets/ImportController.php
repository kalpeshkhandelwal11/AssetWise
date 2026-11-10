<?php

namespace App\Http\Controllers\Assets;

use App\Exports\AssetTemplateExport;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessAssetImport;
use App\Models\AssetCategory;
use App\Models\ImportBatch;
use App\Services\DynamicFieldService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportController extends Controller
{
    public function __construct(private readonly DynamicFieldService $fields)
    {
    }

    public function index(): View
    {
        $this->authorize('imports.manage');

        $batches = ImportBatch::with(['category', 'user'])
            ->orderByDesc('id')
            ->paginate(20);

        return view('modules.assets.import.index', [
            'batches'    => $batches,
            'categories' => AssetCategory::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function template(AssetCategory $category): BinaryFileResponse
    {
        $this->authorize('imports.manage');

        $fileName = 'asset-import-template-'.($category->code ?: $category->id).'.xlsx';

        return Excel::download(new AssetTemplateExport($category->id, $this->fields), $fileName);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('imports.manage');
        $this->authorize('assets.bulk');

        $data = $request->validate([
            'category_id' => 'required|exists:asset_categories,id',
            'file'        => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $file = $request->file('file');
        $storedPath = $file->store('imports', 'local');

        $batch = ImportBatch::create([
            'category_id' => $data['category_id'],
            'user_id'     => $request->user()->id,
            'filename'    => $file->getClientOriginalName(),
            'status'      => 'processing',
        ]);

        ProcessAssetImport::dispatch($batch->id, $storedPath);

        return redirect()->route('assets.import.show', $batch)
            ->with('success', 'Import started — the batch report will update as rows are processed.');
    }

    /**
     * A rejected bulk import keeps its DRAFT assets; resubmitting sends the whole batch for a
     * fresh asset_creation approval (rejection is terminal per request — M08).
     */
    public function resubmit(\Illuminate\Http\Request $request, ImportBatch $batch): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('imports.manage');

        abort_unless(in_array($batch->approval_status, ['rejected', null], true) && $batch->success_count > 0, 422);

        \Illuminate\Support\Facades\DB::transaction(function () use ($batch, $request) {
            $approval = app(\App\Services\WorkflowService::class)->submit($batch, 'asset_creation', $request->user());
            $batch->update(['approval_request_id' => $approval->id, 'approval_status' => 'pending']);
        });

        return back()->with('success', 'Import resubmitted for approval.');
    }

    public function show(ImportBatch $batch): View
    {
        $this->authorize('imports.manage');

        $batch->load('category', 'user');
        $rows = $batch->rows()->with('asset')->orderBy('row_number')->paginate(50);

        return view('modules.assets.import.show', compact('batch', 'rows'));
    }
}
