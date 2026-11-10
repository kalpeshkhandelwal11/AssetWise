<x-app-layout>
    @php $isNhai = $takeover->isNhai(); $draft = $takeover->isDraft(); @endphp
    @section('page-title', 'Takeover '.$takeover->reference_no)

    <x-breadcrumb :items="[['label' => 'NHAI / Toll Plazas', 'url' => route('nhai.index', ['tab' => 'takeovers'])], ['label' => $takeover->reference_no]]" class="mb-4" />

    <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">
                {{ \App\Models\PlazaTakeover::KINDS[$takeover->kind] }} · <span class="font-mono">{{ $takeover->reference_no }}</span>
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                {{ $takeover->location?->name }} · from {{ $takeover->fromCompany?->name }} · for {{ $takeover->company?->name }} ·
                {{ $takeover->takeover_date?->format('d M Y') }}
                @if($takeover->document_no) · doc {{ $takeover->document_no }} @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @include('modules.nhai.partials.status', ['status' => $takeover->status])
            <a href="{{ route('nhai.takeovers.certificate', $takeover) }}" class="px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-200">Certificate (PDF)</a>
            @if($takeover->document_path)
                <a href="{{ Storage::url($takeover->document_path) }}" target="_blank" class="px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-200">Signed document</a>
            @endif
            @can('takeover.manage')
                @if($draft)
                    <a href="{{ route('nhai.takeovers.edit', $takeover) }}" class="px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-200">Edit details</a>
                @elseif($takeover->status === 'rejected')
                    <form method="POST" action="{{ route('nhai.takeovers.reopen', $takeover) }}">@csrf
                        <button class="px-3 py-1.5 text-xs font-medium bg-amber-600 text-white rounded-lg">Reopen as draft</button>
                    </form>
                @endif
            @endcan
            @if($takeover->approvalRequest)
                <a href="{{ route('approvals.show', $takeover->approvalRequest) }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Approval request</a>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Lines</p>
            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $takeover->lines->count() }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Assets</p>
            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $takeover->totalUnits() }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Not functional</p>
            <p class="text-lg font-semibold text-red-600">{{ $takeover->lines->sum('not_functional_qty') }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">{{ $isNhai ? 'Ownership' : 'Purchase value' }}</p>
            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $isNhai ? 'NHAI (no value)' : '₹ '.number_format($takeover->totalValue(), 2) }}</p>
        </div>
    </div>

    @error('workflow')<div class="mb-4 p-3 rounded-lg bg-red-50 text-sm text-red-700">{{ $message }}</div>@enderror
    @error('lines')<div class="mb-4 p-3 rounded-lg bg-red-50 text-sm text-red-700">{{ $message }}</div>@enderror
    @error('status')<div class="mb-4 p-3 rounded-lg bg-red-50 text-sm text-red-700">{{ $message }}</div>@enderror
    @if($errors->has('file'))
        <div class="mb-4 p-3 rounded-lg bg-red-50 text-sm text-red-700">
            <p class="font-medium">The file was not imported:</p>
            <ul class="list-disc pl-5">@foreach($errors->get('file') as $msg)<li>{{ $msg }}</li>@endforeach</ul>
        </div>
    @endif

    <x-data-table>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                <tr class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    <th class="px-3 py-3 text-left">#</th>
                    <th class="px-3 py-3 text-left">Item</th>
                    <th class="px-3 py-3 text-left">Make / model</th>
                    <th class="px-3 py-3 text-left">Serial / position</th>
                    <th class="px-3 py-3 text-right">Qty</th>
                    @unless($isNhai)<th class="px-3 py-3 text-right">Unit price</th>@endunless
                    <th class="px-3 py-3 text-left">Where</th>
                    <th class="px-3 py-3 text-left">Condition</th>
                    <th class="px-3 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @php $section = null; @endphp
                @forelse($takeover->lines as $line)
                    @if($line->section && $line->section !== $section)
                        @php $section = $line->section; @endphp
                        <tr class="bg-gray-50/60 dark:bg-gray-900/30"><td colspan="9" class="px-3 py-2 text-xs font-semibold uppercase text-gray-600 dark:text-gray-300">{{ $section }}</td></tr>
                    @endif
                    <tr>
                        <td class="px-3 py-2 text-gray-400">{{ $loop->iteration }}</td>
                        <td class="px-3 py-2 text-gray-900 dark:text-gray-100">{{ $line->item_name }} <span class="block text-xs text-gray-400">{{ $line->category?->name }}</span></td>
                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ collect([$line->manufacturer, $line->model])->filter()->join(' · ') ?: '—' }}</td>
                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400 text-xs"><span class="font-mono">{{ $line->serial_number }}</span> {{ $line->position_label }}</td>
                        <td class="px-3 py-2 text-right">{{ $line->quantity }}</td>
                        @unless($isNhai)<td class="px-3 py-2 text-right">{{ number_format((float) $line->unit_price, 2) }}</td>@endunless
                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400 text-xs">{{ collect([$line->room?->name, $line->location_note])->filter()->join(' · ') ?: '—' }}</td>
                        <td class="px-3 py-2 text-xs">
                            {{ \App\Models\PlazaTakeoverLine::CONDITIONS[$line->condition] ?? $line->condition }}
                            @if($line->not_functional_qty)<span class="text-red-600">({{ $line->not_functional_qty }} not working)</span>@endif
                            @if($line->remarks)<span class="block text-gray-400">{{ $line->remarks }}</span>@endif
                        </td>
                        <td class="px-3 py-2 text-right">
                            @if($draft)
                                @can('takeover.manage')
                                    <form method="POST" action="{{ route('nhai.takeovers.lines.destroy', [$takeover, $line]) }}">@csrf @method('DELETE')
                                        <button class="text-xs text-red-600 hover:underline">Remove</button>
                                    </form>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-10 text-center text-sm text-gray-400">No lines yet — add them below or import the checklist.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-data-table>

    @if($draft)
        @can('takeover.manage')
            <div class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
                <form method="POST" action="{{ route('nhai.takeovers.lines.store', $takeover) }}"
                      class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6 space-y-4">
                    @csrf
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Add a line</p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="sm:col-span-2"><x-input-label value="Item *" /><x-text-input name="item_name" class="mt-1 block w-full" :value="old('item_name')" required placeholder="e.g. Revolving chair / AVC camera" /></div>
                        <div><x-input-label value="Section" /><x-text-input name="section" class="mt-1 block w-full" :value="old('section')" placeholder="Toll Building / ETC Lanes" /></div>
                        <div>
                            <x-input-label value="Category *" />
                            <select name="category_id" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                                @foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label value="Asset type" />
                            <select name="asset_type_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                                <option value="">—</option>
                                @foreach($types as $type)<option value="{{ $type->id }}">{{ $type->name }}</option>@endforeach
                            </select>
                        </div>
                        <div><x-input-label value="Quantity *" /><x-text-input name="quantity" type="number" min="1" class="mt-1 block w-full" :value="old('quantity', 1)" required /></div>
                        <div><x-input-label value="Make" /><x-text-input name="manufacturer" class="mt-1 block w-full" :value="old('manufacturer')" /></div>
                        <div><x-input-label value="Model" /><x-text-input name="model" class="mt-1 block w-full" :value="old('model')" /></div>
                        <div><x-input-label value="Serial (qty 1)" /><x-text-input name="serial_number" class="mt-1 block w-full font-mono" :value="old('serial_number')" /></div>
                        <div><x-input-label value="Position / lane" /><x-text-input name="position_label" class="mt-1 block w-full" :value="old('position_label')" placeholder="Lane 1 (Booth)" /></div>
                        <div>
                            <x-input-label value="Room" />
                            <select name="room_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                                <option value="">—</option>
                                @foreach($rooms as $room)<option value="{{ $room->id }}">{{ $room->name }}</option>@endforeach
                            </select>
                        </div>
                        <div><x-input-label value="Location note" /><x-text-input name="location_note" class="mt-1 block w-full" :value="old('location_note')" placeholder="IE Room, booth entry…" /></div>
                        <div>
                            <x-input-label value="Condition *" />
                            <select name="condition" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                                @foreach(\App\Models\PlazaTakeoverLine::CONDITIONS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                            </select>
                        </div>
                        <div><x-input-label value="Of which not working" /><x-text-input name="not_functional_qty" type="number" min="0" class="mt-1 block w-full" :value="old('not_functional_qty', 0)" /></div>
                        <div><x-input-label value="Warranty (years)" /><x-text-input name="warranty_years" type="number" step="0.5" min="0" class="mt-1 block w-full" :value="old('warranty_years')" /></div>
                        @unless($isNhai)
                            <div><x-input-label value="Unit price (₹) *" /><x-text-input name="unit_price" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('unit_price')" required /></div>
                        @endunless
                        <div class="sm:col-span-3"><x-input-label value="Remarks" /><x-text-input name="remarks" class="mt-1 block w-full" :value="old('remarks')" placeholder="e.g. Lane 1,2,3 & 4 not working due to lane closed" /></div>
                    </div>
                    <x-input-error :messages="collect(['item_name', 'category_id', 'quantity', 'serial_number', 'unit_price', 'condition'])->flatMap(fn ($k) => $errors->get($k))->all()" class="mt-1" />
                    <x-primary-button>Add line</x-primary-button>
                </form>

                <div class="space-y-6">
                    <form method="POST" action="{{ route('nhai.takeovers.import', $takeover) }}" enctype="multipart/form-data"
                          class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6 space-y-3">
                        @csrf
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Import the checklist</p>
                        <p class="text-xs text-gray-500"><a href="{{ route('nhai.takeovers.template', $takeover) }}" class="text-indigo-600 hover:underline">Download the template</a> — one row per item; quantity rows expand into one asset per unit.</p>
                        <input type="file" name="file" accept=".xlsx,.xls,.csv" required class="block w-full text-sm text-gray-600 dark:text-gray-400">
                        <x-primary-button>Import</x-primary-button>
                    </form>

                    <form method="POST" action="{{ route('nhai.takeovers.submit', $takeover) }}"
                          class="bg-white dark:bg-gray-800 rounded-xl border border-indigo-200 dark:border-indigo-800 shadow-sm p-6 space-y-3"
                          onsubmit="return confirm('Create {{ $takeover->totalUnits() }} draft asset(s) and send for approval?')">
                        @csrf
                        <p class="text-sm text-gray-700 dark:text-gray-300">Ready? Submitting creates <strong>{{ $takeover->totalUnits() }}</strong> asset(s) in Draft and sends the takeover for one approval.</p>
                        <x-primary-button>Submit for approval</x-primary-button>
                    </form>
                </div>
            </div>
        @endcan
    @elseif($assetCount)
        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">{{ $assetCount }} asset(s) were created from this takeover —
            <a href="{{ route('nhai.index', ['tab' => 'assets', 'location_id' => $takeover->location_id]) }}" class="text-indigo-600 hover:underline">view them</a>.</p>
    @endif
</x-app-layout>
