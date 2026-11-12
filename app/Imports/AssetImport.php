<?php

namespace App\Imports;

use App\Imports\Support\MasterLookup;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetStatus;
use App\Models\ImportBatch;
use App\Models\ImportBatchRow;
use App\Models\User;
use App\Rules\UniqueSerialNumber;
use App\Services\AssetService;
use App\Services\DynamicFieldService;
use App\Services\DynamicFields\ResolvedField;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Row;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Row-by-row asset import for a single category (chosen up front on the import batch).
 * `OnEachRow` (rather than `ToCollection`) is used deliberately so every row — good or
 * bad — gets its own `import_batch_rows` entry keyed by the real spreadsheet row number,
 * and one bad row never aborts the rest of the batch.
 *
 * Masters are referenced by NAME or CODE, never by database id (MasterLookup): asset_type,
 * status, location, custodian ("Name (EMP001)" or the code), department, branch, company
 * (company_code / company / company_name). Legacy *_id columns are still accepted when the
 * name column is absent, so pre-existing templates keep working.
 *
 * A file produced by AssetExport re-imports as-is: its extra columns are ignored, "End of
 * Life" Yes/No maps to is_eol, and custom-field columns match by label as well as key.
 *
 * Draft mode ($draftStatusId set, i.e. an active asset_creation workflow): every asset is
 * created as DRAFT and the status the row asked for is parked on its import_batch_rows
 * entry; ProcessAssetImport then submits the whole batch for ONE approval.
 */
class AssetImport implements OnEachRow, WithHeadingRow, WithMultipleSheets, SkipsUnknownSheets
{
    /** Statuses a spreadsheet may not set directly — they are reached only through workflows. */
    private const NON_IMPORTABLE_STATUSES = AssetStatus::NOT_HAND_PICKABLE;

    private const BLANK_SERIALS = ['', 'na', 'n/a', '-', '--', 'nil', 'none', 'null'];

    private MasterLookup $lookup;

    /** @var array<string, int> normalised serial => row number (duplicates inside the file) */
    private array $seenSerials = [];

    /** @var array<int, array<string, int>> field id => normalised value => row number */
    private array $seenUnique = [];

    /** @var array<string, int> asset tag => row number */
    private array $seenTags = [];

    public function __construct(
        private readonly ImportBatch $batch,
        private readonly User $actor,
        private readonly AssetService $assets,
        private readonly DynamicFieldService $fields,
        private readonly ?int $draftStatusId = null,
    ) {
        $this->lookup = new MasterLookup;
    }

    /** Only the first (data) sheet — the template's hidden "Lists" sheet must not be imported. */
    public function sheets(): array
    {
        return [0 => $this];
    }

    public function onUnknownSheet($sheetName): void
    {
        // A workbook with fewer sheets than expected is fine.
    }

    public function onRow(Row $row): void
    {
        if ($row->isEmpty()) {
            return;
        }

        $data = $row->toArray();
        $rowNumber = $row->getIndex();

        // The template's example row — never import it, even if left in by mistake.
        if (Str::startsWith(trim((string) ($data['name'] ?? '')), 'SAMPLE — delete this row')) {
            return;
        }

        $errors = [];

        // 1. masters by name/code -> ids
        $companyId = $this->resolveCompany($data, $errors);
        $ids = $this->resolveMasters($data, $errors);

        // A re-imported export carries a Category column; it must match the batch's category.
        if (filled($data['category'] ?? null)) {
            $category = AssetCategory::find($this->batch->category_id);
            $given = MasterLookup::normalise($data['category']);

            if ($category && ! in_array($given, [MasterLookup::normalise($category->name), MasterLookup::normalise($category->code)], true)) {
                $errors['category'] = ["Category \"{$data['category']}\" does not match this import's category ({$category->name})."];
            }
        }

        // 2. core columns
        $input = $this->coreInput($data) + $ids;
        $core = [];

        try {
            $core = validator($input, $this->coreRules())->validate();
        } catch (ValidationException $e) {
            $errors = array_merge($errors, $e->errors());
        }

        $this->checkInFileDuplicates($core, $rowNumber, $errors);

        // 3. dynamic fields for the batch's category, via the single source of truth
        $resolved = $this->fields->resolveForCategory($this->batch->category_id);
        $fieldInput = $this->dynamicInput($data, $resolved);

        $dynamic = [];
        try {
            $dynamic = $this->fields->validate($fieldInput, $resolved);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $errors["fields.$key"] = $messages;
            }
        }

        $this->checkUniqueFieldsInFile($dynamic, $resolved, $rowNumber, $errors);

        if ($errors) {
            $this->recordRow($rowNumber, 'failed', $errors);

            return;
        }

        $intendedStatusId = $core['status_id'];

        if ($this->draftStatusId) {
            $core['status_id'] = $this->draftStatusId;
        }

        $asset = $this->assets->create($core + ['company_id' => $companyId, 'category_id' => $this->batch->category_id], $this->actor);
        $this->fields->saveValues($asset, $dynamic, $resolved);

        $this->recordRow($rowNumber, 'success', null, $asset->id, $this->draftStatusId ? $intendedStatusId : null);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $errors
     */
    private function resolveCompany(array $data, array &$errors): ?int
    {
        $raw = $data['company_code'] ?? $data['company'] ?? $data['company_name'] ?? null;

        if (blank($raw)) {
            $errors['company_code'] = ['The company code is required.'];

            return null;
        }

        $hit = $this->lookup->resolve('company', $raw);

        if ($hit['error']) {
            $errors['company_code'] = [$hit['error'] === sprintf('Unknown company "%s".', trim((string) $raw))
                ? sprintf('No active company found with code "%s".', trim((string) $raw))
                : $hit['error']];
        }

        return $hit['id'];
    }

    /**
     * Name/code columns -> *_id. Falls back to a legacy numeric *_id column when the name
     * column is absent.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $errors
     * @return array<string, mixed>
     */
    private function resolveMasters(array $data, array &$errors): array
    {
        $map = [
            'asset_type' => 'asset_type_id',
            'status'     => 'status_id',
            'location'   => 'location_id',
            'custodian'  => 'custodian_id',
            'department' => 'department_id',
            'branch'     => 'branch_id',
        ];

        $out = [];

        foreach ($map as $column => $idColumn) {
            $raw = $data[$column] ?? null;

            // An export carries "Custodian Code" next to "Custodian" — the code is exact.
            if ($column === 'custodian' && filled($data['custodian_code'] ?? null)) {
                $raw = $data['custodian_code'];
            }

            if (blank($raw)) {
                $out[$idColumn] = $data[$idColumn] ?? null; // legacy id template (or blank)

                continue;
            }

            $hit = $this->lookup->resolve($column, $raw);

            if ($hit['error']) {
                $errors[$column] = [$hit['error']];
            }

            $out[$idColumn] = $hit['id'];
        }

        if (! empty($out['status_id'])) {
            $code = AssetStatus::whereKey($out['status_id'])->value('code');

            if (in_array($code, self::NON_IMPORTABLE_STATUSES, true)) {
                $errors['status'] = ["Status \"{$code}\" cannot be set by import — it is reached through its own workflow."];
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $data */
    private function coreInput(array $data): array
    {
        $serial = trim((string) ($data['serial_number'] ?? ''));

        $input = [
            'asset_tag'          => filled($data['asset_tag'] ?? null) ? trim((string) $data['asset_tag']) : null,
            'name'               => $data['name'] ?? null,
            'description'        => $data['description'] ?? null,
            'serial_number'      => in_array(Str::lower($serial), self::BLANK_SERIALS, true) ? null : $serial,
            'model'              => $data['model'] ?? null,
            'manufacturer'       => $data['manufacturer'] ?? null,
            'purchase_date'      => $this->date($data['purchase_date'] ?? null),
            'purchase_cost'      => $data['purchase_cost'] ?? null,
            'vendor'             => $data['vendor'] ?? null,
            'vendor_invoice_no'  => $data['vendor_invoice_no'] ?? null,
            'warranty_expiry'    => $this->date($data['warranty_expiry'] ?? null),
            'amc_expiry'         => $this->date($data['amc_expiry'] ?? null),
            'notes'              => $data['notes'] ?? null,
        ];

        $eol = $data['is_eol'] ?? $data['end_of_life'] ?? null;
        if (filled($eol)) {
            $input['is_eol'] = in_array(Str::lower(trim((string) $eol)), ['1', 'yes', 'y', 'true'], true);
        }

        return $input;
    }

    private function coreRules(): array
    {
        return [
            'asset_tag'         => ['nullable', 'string', 'max:255', Rule::unique('assets', 'asset_tag')],
            'name'              => 'required|string|max:255',
            'description'       => 'nullable|string',
            'serial_number'     => ['nullable', 'string', 'max:255', new UniqueSerialNumber],
            'model'             => 'nullable|string|max:255',
            'manufacturer'      => 'nullable|string|max:255',
            'asset_type_id'     => 'required|exists:asset_types,id',
            'status_id'         => 'required|exists:asset_statuses,id',
            'location_id'       => 'nullable|exists:locations,id',
            'custodian_id'      => 'nullable|exists:employees,id',
            'department_id'     => 'nullable|exists:departments,id',
            'branch_id'         => 'nullable|exists:branches,id',
            'purchase_date'     => 'nullable|date',
            'purchase_cost'     => 'nullable|numeric|min:0',
            'vendor'            => 'nullable|string|max:255',
            'vendor_invoice_no' => 'nullable|string|max:255',
            'warranty_expiry'   => 'nullable|date',
            'amc_expiry'        => 'nullable|date',
            'is_eol'            => 'sometimes|boolean',
            'notes'             => 'nullable|string',
        ];
    }

    /**
     * Custom-field cells keyed by field_key. Accepts the field_key heading (template) or the
     * slugged label (AssetExport), and a dropdown option's label as well as its value.
     *
     * @param  array<string, mixed>  $data
     * @param  Collection<int, ResolvedField>  $resolved
     */
    private function dynamicInput(array $data, Collection $resolved): array
    {
        $input = [];

        foreach ($resolved as $field) {
            $heading = array_key_exists($field->fieldKey, $data)
                ? $field->fieldKey
                : (array_key_exists(Str::slug($field->label, '_'), $data) ? Str::slug($field->label, '_') : null);

            if ($heading === null) {
                continue;
            }

            $value = $data[$heading];

            if ($field->fieldType === 'date') {
                $value = $this->date($value);
            } elseif ($field->fieldType === 'boolean' && is_string($value)) {
                $value = match (Str::lower(trim($value))) {
                    'yes', 'y', 'true'  => 1,
                    'no', 'n', 'false'  => 0,
                    default             => $value,
                };
            } elseif ($field->fieldType === 'dropdown' && filled($value)) {
                $match = collect($field->options)->first(fn ($o) => MasterLookup::normalise($o['label']) === MasterLookup::normalise((string) $value)
                    || MasterLookup::normalise($o['value']) === MasterLookup::normalise((string) $value));
                $value = $match['value'] ?? $value;
            }

            $input[$field->fieldKey] = $value;
        }

        return $input;
    }

    /** @param array<string, mixed> $errors */
    private function checkInFileDuplicates(array $core, int $rowNumber, array &$errors): void
    {
        if (! empty($core['serial_number'])) {
            $key = MasterLookup::normalise($core['serial_number']);

            if (isset($this->seenSerials[$key])) {
                $errors['serial_number'][] = "Serial number duplicates row {$this->seenSerials[$key]} in this file.";
            } else {
                $this->seenSerials[$key] = $rowNumber;
            }
        }

        if (! empty($core['asset_tag'])) {
            $key = MasterLookup::normalise($core['asset_tag']);

            if (isset($this->seenTags[$key])) {
                $errors['asset_tag'][] = "Asset tag duplicates row {$this->seenTags[$key]} in this file.";
            } else {
                $this->seenTags[$key] = $rowNumber;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $dynamic
     * @param  Collection<int, ResolvedField>  $resolved
     * @param  array<string, mixed>  $errors
     */
    private function checkUniqueFieldsInFile(array $dynamic, Collection $resolved, int $rowNumber, array &$errors): void
    {
        foreach ($resolved->where('isUnique', true) as $field) {
            $value = $dynamic[$field->fieldKey] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $key = MasterLookup::normalise((string) $value);

            if (isset($this->seenUnique[$field->id][$key])) {
                $errors["fields.{$field->fieldKey}"][] = "{$field->label} duplicates row {$this->seenUnique[$field->id][$key]} in this file.";
            } else {
                $this->seenUnique[$field->id][$key] = $rowNumber;
            }
        }
    }

    /** Excel stores dates as serial numbers; accept those as well as text dates. */
    private function date(mixed $value): mixed
    {
        if (is_numeric($value) && (float) $value > 59 && (float) $value < 2958466) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        return blank($value) ? null : $value;
    }

    /**
     * @param  array<string, mixed>|null  $errors
     */
    private function recordRow(int $rowNumber, string $status, ?array $errors, ?int $assetId = null, ?int $intendedStatusId = null): void
    {
        ImportBatchRow::create([
            'batch_id'           => $this->batch->id,
            'row_number'         => $rowNumber,
            'status'             => $status,
            'errors'             => $errors,
            'asset_id'           => $assetId,
            'intended_status_id' => $intendedStatusId,
        ]);
    }
}
