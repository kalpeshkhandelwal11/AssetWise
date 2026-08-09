<?php

namespace App\Imports;

use App\Models\Company;
use App\Models\ImportBatch;
use App\Models\ImportBatchRow;
use App\Models\User;
use App\Services\AssetService;
use App\Services\DynamicFieldService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;

/**
 * Row-by-row asset import for a single category (chosen up front on the import batch).
 * `OnEachRow` (rather than `ToCollection`) is used deliberately so every row — good or
 * bad — gets its own `import_batch_rows` entry keyed by the real spreadsheet row number,
 * and one bad row never aborts the rest of the batch.
 */
class AssetImport implements OnEachRow, WithHeadingRow
{
    public function __construct(
        private readonly ImportBatch $batch,
        private readonly User $actor,
        private readonly AssetService $assets,
        private readonly DynamicFieldService $fields,
    ) {
    }

    public function onRow(Row $row): void
    {
        if ($row->isEmpty()) {
            return;
        }

        $data = $row->toArray();
        $errors = [];

        // 1. company_code -> company_id (a spreadsheet can't reference a DB id directly)
        $companyId = $this->resolveCompany($data, $errors);

        // 2. core columns — mirrors the core-fields half of AssetController::validateAsset()
        $core = [];
        try {
            $core = validator($data, $this->coreRules())->validate();
        } catch (ValidationException $e) {
            $errors = array_merge($errors, $e->errors());
        }

        // 3. dynamic fields for the batch's category, via the single source of truth
        $resolved = $this->fields->resolveForCategory($this->batch->category_id);
        $fieldInput = [];
        foreach ($resolved as $field) {
            if (array_key_exists($field->fieldKey, $data)) {
                $fieldInput[$field->fieldKey] = $data[$field->fieldKey];
            }
        }

        $dynamic = [];
        try {
            $dynamic = $this->fields->validate($fieldInput, $resolved);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $errors["fields.$key"] = $messages;
            }
        }

        if ($errors) {
            $this->recordRow($row->getIndex(), 'failed', $errors);

            return;
        }

        $asset = $this->assets->create($core + ['company_id' => $companyId, 'category_id' => $this->batch->category_id], $this->actor);
        $this->fields->saveValues($asset, $dynamic, $resolved);

        $this->recordRow($row->getIndex(), 'success', null, $asset->id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $errors
     */
    private function resolveCompany(array $data, array &$errors): ?int
    {
        $code = trim((string) ($data['company_code'] ?? ''));

        if ($code === '') {
            $errors['company_code'] = ['The company code is required.'];

            return null;
        }

        $company = Company::where('code', $code)->where('is_active', true)->first();

        if (! $company) {
            $errors['company_code'] = ["No active company found with code \"{$code}\"."];

            return null;
        }

        return $company->id;
    }

    private function coreRules(): array
    {
        return [
            'name'            => 'required|string|max:255',
            'description'     => 'nullable|string',
            'serial_number'   => 'nullable|string|max:255',
            'model'           => 'nullable|string|max:255',
            'manufacturer'    => 'nullable|string|max:255',
            'asset_type_id'   => 'required|exists:asset_types,id',
            'status_id'       => 'required|exists:asset_statuses,id',
            'location_id'     => 'nullable|exists:locations,id',
            'custodian_id'    => 'nullable|exists:users,id',
            'department_id'   => 'nullable|exists:departments,id',
            'branch_id'       => 'nullable|exists:branches,id',
            'purchase_date'   => 'nullable|date',
            'purchase_cost'   => 'nullable|numeric|min:0',
            'vendor'          => 'nullable|string|max:255',
            'warranty_expiry' => 'nullable|date',
            'amc_expiry'      => 'nullable|date',
            'notes'           => 'nullable|string',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $errors
     */
    private function recordRow(int $rowNumber, string $status, ?array $errors, ?int $assetId = null): void
    {
        ImportBatchRow::create([
            'batch_id'   => $this->batch->id,
            'row_number' => $rowNumber,
            'status'     => $status,
            'errors'     => $errors,
            'asset_id'   => $assetId,
        ]);
    }
}
