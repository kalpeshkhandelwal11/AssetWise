@php
    $attachmentTypeLabels = [
        'invoice' => 'Invoice',
        'warranty_card' => 'Warranty Card',
        'manual' => 'Manual',
        'agreement' => 'Agreement',
        'photo' => 'Photo',
    ];
@endphp
<x-app-layout>
    @section('page-title', $asset->name)

    <div x-data="{ tab: 'summary' }">
        @if($asset->isDraft())
            <div class="mb-4 rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 px-4 py-3 flex items-center justify-between gap-3">
                <p class="text-sm text-amber-800 dark:text-amber-300">
                    This asset is a <strong>draft</strong>.
                    @if($asset->hasPendingCreationApproval())
                        It is pending creation approval.
                    @else
                        It has not been approved yet — edit if needed and resubmit.
                    @endif
                </p>
                @can('assets.edit')
                    @unless($asset->hasPendingCreationApproval())
                        <form method="POST" action="{{ route('assets.submit-approval', $asset) }}" class="flex-shrink-0">
                            @csrf
                            <button type="submit" class="px-3 py-1.5 text-xs font-medium bg-amber-600 text-white rounded-lg hover:bg-amber-700">
                                Submit for approval
                            </button>
                        </form>
                    @endunless
                @endcan
            </div>
        @endif
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <a href="{{ route('assets.index') }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <div>
                    <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $asset->name }}</h1>
                    <p class="text-xs text-gray-400 font-mono">{{ $asset->asset_tag ?? $asset->serial_number ?? 'No tag assigned' }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                @can('disposal.request')
                    @unless($asset->isDisposed() || $asset->hasPendingDisposal())
                        <a href="{{ route('disposals.create', ['asset_id' => $asset->id]) }}"
                           class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-lg border border-gray-300 dark:border-gray-700 transition-colors">
                            Request Disposal
                        </a>
                    @endunless
                @endcan
                @can('assets.edit')
                    <a href="{{ route('assets.edit', $asset) }}"
                       class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-lg border border-gray-300 dark:border-gray-700 transition-colors">
                        Edit
                    </a>
                @endcan
                @can('assets.delete')
                    <form method="POST" action="{{ route('assets.destroy', $asset) }}" onsubmit="return confirm('Delete this asset?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800 transition-colors">
                            Delete
                        </button>
                    </form>
                @endcan
            </div>
        </div>

        {{-- Tabs --}}
        <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
            <nav class="flex gap-6 -mb-px">
                @php $kitMemberships = $asset->kitAssets->pluck('kitItem.kit')->filter()->unique('id')->values(); @endphp
                @foreach(['summary' => 'Summary', 'photos' => 'Photos ('.$asset->photos->count().')', 'attachments' => 'Attachments ('.$asset->attachments->count().')', 'tags' => 'Tags ('.$asset->tagAssignments->count().')', 'movements' => 'Movements ('.$asset->movements->count().')', 'maintenance' => 'Maintenance ('.($asset->maintenanceRecords->count() + $asset->amcContracts->count() + $asset->warrantyRecords->count()).')', 'kits' => 'Kits ('.$kitMemberships->count().')', 'history' => 'History'] as $key => $label)
                    <button @click="tab = '{{ $key }}'"
                            :class="tab === '{{ $key }}' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
                            class="pb-3 text-sm font-medium border-b-2 transition-colors">
                        {{ $label }}
                    </button>
                @endforeach
            </nav>
        </div>

        {{-- Summary tab --}}
        <div x-show="tab === 'summary'" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4 text-sm">
                <div><dt class="text-gray-400 text-xs uppercase tracking-wide">Company</dt><dd class="text-gray-900 dark:text-gray-100 mt-0.5">{{ $asset->company?->name ?? '—' }}</dd></div>
                <div><dt class="text-gray-400 text-xs uppercase tracking-wide">Category</dt><dd class="text-gray-900 dark:text-gray-100 mt-0.5">{{ $asset->category?->name ?? '—' }}</dd></div>
                <div><dt class="text-gray-400 text-xs uppercase tracking-wide">Type</dt><dd class="text-gray-900 dark:text-gray-100 mt-0.5">{{ $asset->assetType?->name ?? '—' }}</dd></div>
                <div><dt class="text-gray-400 text-xs uppercase tracking-wide">Status</dt><dd class="mt-0.5">
                    @if($asset->status)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium" style="background-color: {{ $asset->status->color }}20; color: {{ $asset->status->color }}">{{ $asset->status->name }}</span>
                    @else — @endif
                </dd></div>
                <div><dt class="text-gray-400 text-xs uppercase tracking-wide">Serial Number</dt><dd class="text-gray-900 dark:text-gray-100 mt-0.5">{{ $asset->serial_number ?? '—' }}</dd></div>
                <div><dt class="text-gray-400 text-xs uppercase tracking-wide">Model</dt><dd class="text-gray-900 dark:text-gray-100 mt-0.5">{{ $asset->model ?? '—' }}</dd></div>
                <div><dt class="text-gray-400 text-xs uppercase tracking-wide">Manufacturer</dt><dd class="text-gray-900 dark:text-gray-100 mt-0.5">{{ $asset->manufacturer ?? '—' }}</dd></div>
                <div><dt class="text-gray-400 text-xs uppercase tracking-wide">Custodian</dt><dd class="text-gray-900 dark:text-gray-100 mt-0.5">{{ $asset->custodian?->name ?? '—' }}</dd></div>
                <div><dt class="text-gray-400 text-xs uppercase tracking-wide">Department</dt><dd class="text-gray-900 dark:text-gray-100 mt-0.5">{{ $asset->department?->name ?? '—' }}</dd></div>
                <div><dt class="text-gray-400 text-xs uppercase tracking-wide">Branch</dt><dd class="text-gray-900 dark:text-gray-100 mt-0.5">{{ $asset->branch?->name ?? '—' }}</dd></div>
                <div><dt class="text-gray-400 text-xs uppercase tracking-wide">Location</dt><dd class="text-gray-900 dark:text-gray-100 mt-0.5">{{ collect([$asset->location?->name, $asset->building?->name, $asset->floor?->name, $asset->room?->name])->filter()->join(' / ') ?: '—' }}</dd></div>
                @can('assets.view_financials')
                <div><dt class="text-gray-400 text-xs uppercase tracking-wide">Purchase Date</dt><dd class="text-gray-900 dark:text-gray-100 mt-0.5">{{ optional($asset->purchase_date)->format('d M Y') ?? '—' }}</dd></div>
                <div><dt class="text-gray-400 text-xs uppercase tracking-wide">Purchase Cost</dt><dd class="text-gray-900 dark:text-gray-100 mt-0.5">{{ $asset->purchase_cost !== null ? number_format($asset->purchase_cost, 2) : '—' }}</dd></div>
                <div><dt class="text-gray-400 text-xs uppercase tracking-wide">Vendor</dt><dd class="text-gray-900 dark:text-gray-100 mt-0.5">{{ $asset->vendor ?? '—' }}</dd></div>
                @endcan
                <div><dt class="text-gray-400 text-xs uppercase tracking-wide">Warranty Expiry</dt><dd class="text-gray-900 dark:text-gray-100 mt-0.5">{{ optional($asset->warranty_expiry)->format('d M Y') ?? '—' }}</dd></div>
                <div><dt class="text-gray-400 text-xs uppercase tracking-wide">AMC Expiry</dt><dd class="text-gray-900 dark:text-gray-100 mt-0.5">{{ optional($asset->amc_expiry)->format('d M Y') ?? '—' }}</dd></div>
                <div><dt class="text-gray-400 text-xs uppercase tracking-wide">End of Life</dt><dd class="mt-0.5">
                    @if($asset->is_eol)
                        <x-status-badge color="red" label="{{ 'EOL'.($asset->eol_projected_date ? ' · '.$asset->eol_projected_date->format('d M Y') : '') }}" />
                    @else — @endif
                </dd></div>
                <div><dt class="text-gray-400 text-xs uppercase tracking-wide">Created By</dt><dd class="text-gray-900 dark:text-gray-100 mt-0.5">{{ $asset->creator?->name ?? '—' }}</dd></div>
            </dl>
            @if($asset->description)
                <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
                    <dt class="text-gray-400 text-xs uppercase tracking-wide">Description</dt>
                    <dd class="text-gray-700 dark:text-gray-300 mt-1 text-sm">{{ $asset->description }}</dd>
                </div>
            @endif
            @if($asset->notes)
                <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
                    <dt class="text-gray-400 text-xs uppercase tracking-wide">Notes</dt>
                    <dd class="text-gray-700 dark:text-gray-300 mt-1 text-sm">{{ $asset->notes }}</dd>
                </div>
            @endif
            @if($asset->fieldValues->isNotEmpty())
                <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Custom Fields</p>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4 text-sm">
                        @foreach($asset->fieldValues->sortBy(fn ($fv) => $fv->categoryField?->display_order) as $fv)
                            @if($fv->categoryField)
                                <div>
                                    <dt class="text-gray-400 text-xs uppercase tracking-wide">
                                        {{ $fv->categoryField->label }}
                                        @if($fv->categoryField->trashed() || ! $fv->categoryField->is_active)
                                            <span class="text-amber-500" title="Field no longer active on this category">(archived)</span>
                                        @endif
                                    </dt>
                                    <dd class="text-gray-900 dark:text-gray-100 mt-0.5">{{ $fv->displayValue() }}</dd>
                                </div>
                            @endif
                        @endforeach
                    </dl>
                </div>
            @endif
        </div>

        {{-- Photos tab --}}
        <div x-show="tab === 'photos'" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            @can('assets.edit')
            <form method="POST" action="{{ route('assets.photos.store', $asset) }}" enctype="multipart/form-data" class="flex items-center gap-3 mb-5">
                @csrf
                <input type="file" name="photos[]" multiple accept="image/*" required class="text-sm text-gray-600 dark:text-gray-400">
                <x-primary-button type="submit">Upload</x-primary-button>
            </form>
            @endcan

            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-4">
                @forelse($asset->photos as $photo)
                    <div class="relative group">
                        <img src="{{ Storage::url($photo->path) }}" alt="Asset photo" class="w-full aspect-square object-cover rounded-lg border border-gray-200 dark:border-gray-700">
                        @if($photo->is_primary)
                            <span class="absolute top-1 left-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-indigo-600 text-white">Primary</span>
                        @endif
                        @can('assets.edit')
                        <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg flex items-center justify-center gap-1">
                            @if(! $photo->is_primary)
                                <form method="POST" action="{{ route('assets.photos.primary', [$asset, $photo]) }}">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="p-1.5 bg-white/90 rounded-md text-gray-700 hover:text-indigo-600" title="Set primary">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                                    </button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('assets.photos.destroy', [$asset, $photo]) }}" onsubmit="return confirm('Remove this photo?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="p-1.5 bg-white/90 rounded-md text-gray-700 hover:text-red-600" title="Remove">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </form>
                        </div>
                        @endcan
                    </div>
                @empty
                    <p class="col-span-full text-sm text-gray-400 py-8 text-center">No photos uploaded.</p>
                @endforelse
            </div>
        </div>

        {{-- Attachments tab --}}
        <div x-show="tab === 'attachments'" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            @can('assets.edit')
            <form method="POST" action="{{ route('assets.attachments.store', $asset) }}" enctype="multipart/form-data" class="flex items-center gap-3 mb-5">
                @csrf
                <select name="type" required class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                    @foreach($attachmentTypeLabels as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <input type="file" name="file" required class="text-sm text-gray-600 dark:text-gray-400">
                <x-primary-button type="submit">Upload</x-primary-button>
            </form>
            @endcan

            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($asset->attachments as $attachment)
                    <div class="flex items-center justify-between py-3">
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            <div>
                                <a href="{{ Storage::url($attachment->path) }}" target="_blank" class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline">{{ $attachment->original_name }}</a>
                                <p class="text-xs text-gray-400">{{ $attachmentTypeLabels[$attachment->type] ?? $attachment->type }} &middot; {{ number_format($attachment->size / 1024, 0) }} KB</p>
                            </div>
                        </div>
                        @can('assets.edit')
                        <form method="POST" action="{{ route('assets.attachments.destroy', [$asset, $attachment]) }}" onsubmit="return confirm('Remove this attachment?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="p-1.5 text-gray-400 hover:text-red-600 dark:hover:text-red-400 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </form>
                        @endcan
                    </div>
                @empty
                    <p class="text-sm text-gray-400 py-8 text-center">No attachments uploaded.</p>
                @endforelse
            </div>
        </div>

        {{-- Tags tab (M05) --}}
        @php $currentTagAssignment = $asset->tagAssignments->firstWhere('status', 'active'); @endphp
        <div x-show="tab === 'tags'" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Current Tag</p>
                    @if($currentTagAssignment)
                        <p class="font-mono text-lg text-gray-900 dark:text-gray-100 mt-0.5">{{ $currentTagAssignment->tag->tag_number }}</p>
                        <p class="text-xs text-gray-400">{{ ucfirst($currentTagAssignment->tag->code_type) }} &middot; assigned {{ $currentTagAssignment->assigned_at->format('d M Y') }} by {{ $currentTagAssignment->assignedBy?->name ?? '—' }}</p>
                    @else
                        <p class="text-sm text-gray-400 mt-0.5">No tag assigned</p>
                    @endif
                </div>
                @can('tags.replace')
                    @if($currentTagAssignment)
                        <a href="{{ route('assets.tags.replace', $asset) }}"
                           class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-lg border border-gray-300 dark:border-gray-700 transition-colors">
                            Replace Tag
                        </a>
                    @endif
                @endcan
            </div>

            @can('tags.assign')
                @if(! $currentTagAssignment)
                    <form method="POST" action="{{ route('assets.tags.assign', $asset) }}" class="flex flex-wrap items-end gap-3 mb-6 pb-6 border-b border-gray-100 dark:border-gray-700">
                        @csrf
                        <div>
                            <x-input-label for="tag_id" value="Pick from pool" />
                            <select id="tag_id" name="tag_id" class="mt-1 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                <option value="">— Select available tag —</option>
                                @foreach($availableTags as $tag)
                                    <option value="{{ $tag->id }}">{{ $tag->tag_number }}</option>
                                @endforeach
                            </select>
                        </div>
                        <span class="text-xs text-gray-400 pb-2">or</span>
                        <div>
                            <x-input-label for="tag_number" value="Scan-to-assign (tag number)" />
                            <x-text-input id="tag_number" name="tag_number" class="mt-1 block w-full" />
                        </div>
                        <x-primary-button type="submit">Assign Tag</x-primary-button>
                        <x-input-error :messages="$errors->get('tag')" class="mt-1 w-full" />
                        <x-input-error :messages="$errors->get('asset')" class="mt-1 w-full" />
                    </form>
                @endif
            @endcan

            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Tag History</p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tag</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Assigned</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Deactivated</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($asset->tagAssignments as $assignment)
                            <tr>
                                <td class="px-4 py-2 font-mono text-gray-900 dark:text-gray-100">{{ $assignment->tag->tag_number }}</td>
                                <td class="px-4 py-2">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium {{ $assignment->status === 'active' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400' }}">
                                        {{ ucfirst($assignment->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $assignment->assigned_at->format('d M Y') }} &middot; {{ $assignment->assignedBy?->name ?? '—' }}</td>
                                <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $assignment->deactivated_at?->format('d M Y') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-400">No tag history.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Movements tab (M09) --}}
        <div x-show="tab === 'movements'" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            <div class="flex items-center justify-between mb-5">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Movement History</p>
                @canany(['movement.assign', 'movement.transfer'])
                    @unless($asset->isDisposed())
                        <a href="{{ route('movements.create', ['asset_id' => $asset->id]) }}"
                           class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-lg border border-gray-300 dark:border-gray-700 transition-colors">
                            Move This Asset
                        </a>
                    @endunless
                @endcanany
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Destination</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Requested By</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($asset->movements as $movement)
                            <tr>
                                <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ $movement->movementType?->name }}</td>
                                <td class="px-4 py-2 text-gray-700 dark:text-gray-300 text-xs">
                                    {{ collect([$movement->toCompany?->name, $movement->toLocation?->name, $movement->toCustodian?->name, $movement->toDepartment?->name])->filter()->join(' · ') ?: '—' }}
                                </td>
                                <td class="px-4 py-2">
                                    @php
                                        $moveStatusColor = match($movement->status) {
                                            'completed' => 'green',
                                            'rejected'  => 'red',
                                            default     => 'amber',
                                        };
                                    @endphp
                                    <x-status-badge :color="$moveStatusColor" :label="ucwords(str_replace('_', ' ', $movement->status))" />
                                </td>
                                <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $movement->requestedBy?->name ?? '—' }}</td>
                                <td class="px-4 py-2 text-gray-500 dark:text-gray-400">{{ $movement->created_at->format('d M Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-400">No movements recorded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Maintenance tab (M11) --}}
        <div x-show="tab === 'maintenance'" class="space-y-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Maintenance Records</p>
                        <p class="text-xs text-gray-400 mt-0.5">Total repair/maintenance cost: {{ number_format($asset->maintenanceRecords->sum('cost'), 2) }}</p>
                    </div>
                    @can('maintenance.manage')
                        <a href="{{ route('assets.maintenance.create', $asset) }}"
                           class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-lg border border-gray-300 dark:border-gray-700 transition-colors">
                            Log Maintenance
                        </a>
                    @endcan
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Vendor</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Cost</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                                @can('maintenance.manage')<th class="px-4 py-2"></th>@endcan
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($asset->maintenanceRecords as $record)
                                <tr>
                                    <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ $record->maintenanceType?->name }}</td>
                                    <td class="px-4 py-2">
                                        @php
                                            $maintStatusColor = match($record->status) {
                                                'completed' => 'green', 'in_progress' => 'amber', 'cancelled' => 'red', default => 'gray',
                                            };
                                        @endphp
                                        <x-status-badge :color="$maintStatusColor" :label="ucwords(str_replace('_', ' ', $record->status))" />
                                    </td>
                                    <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $record->vendor ?? '—' }}</td>
                                    <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $record->cost !== null ? number_format($record->cost, 2) : '—' }}</td>
                                    <td class="px-4 py-2 text-gray-500 dark:text-gray-400">{{ $record->created_at->format('d M Y') }}</td>
                                    @can('maintenance.manage')
                                    <td class="px-4 py-2 text-right">
                                        <a href="{{ route('assets.maintenance.edit', [$asset, $record]) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Edit</a>
                                    </td>
                                    @endcan
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-gray-400">No maintenance records.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
                <div class="flex items-center justify-between mb-5">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">AMC Contracts</p>
                    @can('maintenance.manage')
                        <a href="{{ route('assets.amc.create', $asset) }}"
                           class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-lg border border-gray-300 dark:border-gray-700 transition-colors">
                            Add AMC Contract
                        </a>
                    @endcan
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Vendor</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Coverage Period</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Cost</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                @can('maintenance.manage')<th class="px-4 py-2"></th>@endcan
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($asset->amcContracts as $contract)
                                <tr>
                                    <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ $contract->vendor }}</td>
                                    <td class="px-4 py-2 text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $contract->start_date->format('d M Y') }} &ndash; {{ $contract->end_date->format('d M Y') }}</td>
                                    <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $contract->cost !== null ? number_format($contract->cost, 2) : '—' }}</td>
                                    <td class="px-4 py-2"><x-status-badge :color="$contract->end_date->isPast() ? 'red' : 'green'" :label="$contract->end_date->isPast() ? 'Expired' : 'Active'" /></td>
                                    @can('maintenance.manage')
                                    <td class="px-4 py-2 text-right">
                                        <a href="{{ route('assets.amc.edit', [$asset, $contract]) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Edit</a>
                                    </td>
                                    @endcan
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-gray-400">No AMC contracts.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
                <div class="flex items-center justify-between mb-5">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Warranty Records</p>
                    @can('maintenance.manage')
                        <a href="{{ route('assets.warranty.create', $asset) }}"
                           class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-lg border border-gray-300 dark:border-gray-700 transition-colors">
                            Add Warranty Record
                        </a>
                    @endcan
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Provider</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Coverage Period</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                @can('maintenance.manage')<th class="px-4 py-2"></th>@endcan
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($asset->warrantyRecords as $warranty)
                                <tr>
                                    <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ $warranty->provider }}</td>
                                    <td class="px-4 py-2 text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ optional($warranty->start_date)->format('d M Y') ?? '—' }} &ndash; {{ $warranty->end_date->format('d M Y') }}</td>
                                    <td class="px-4 py-2"><x-status-badge :color="$warranty->end_date->isPast() ? 'red' : 'green'" :label="$warranty->end_date->isPast() ? 'Expired' : 'Active'" /></td>
                                    @can('maintenance.manage')
                                    <td class="px-4 py-2 text-right">
                                        <a href="{{ route('assets.warranty.edit', [$asset, $warranty]) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Edit</a>
                                    </td>
                                    @endcan
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-gray-400">No warranty records.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Kits tab (M17) — which kit templates include this asset --}}
        <div x-show="tab === 'kits'" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">Kit Memberships</p>
            @if($kitMemberships->isEmpty())
                <p class="text-sm text-gray-400">This asset isn't linked to any kit template.</p>
            @else
                <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($kitMemberships as $kit)
                        <li class="flex items-center justify-between py-2.5 text-sm">
                            <div>
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ $kit->name }}</span>
                                <span class="text-xs text-gray-400 ml-2 font-mono">{{ $kit->code }}</span>
                            </div>
                            @can('kits.manage')
                                <a href="{{ route('kits.edit', $kit) }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Open kit</a>
                            @endcan
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- History tab --}}
        <div x-show="tab === 'history'" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            <ul class="space-y-4">
                @forelse($activities as $activity)
                    <li class="flex gap-3 text-sm">
                        <div class="w-2 h-2 mt-1.5 rounded-full bg-indigo-500 flex-shrink-0"></div>
                        <div>
                            <p class="text-gray-900 dark:text-gray-100">
                                <span class="font-medium">{{ $activity->causer?->name ?? 'System' }}</span>
                                {{ $activity->description }}
                            </p>
                            <p class="text-xs text-gray-400">{{ $activity->created_at->format('d M Y, H:i') }}</p>
                            @if($activity->event === 'updated' && $activity->properties->has('attributes'))
                                <ul class="mt-1 text-xs text-gray-500 dark:text-gray-400 space-y-0.5">
                                    @foreach($activity->properties['attributes'] as $field => $new)
                                        <li>
                                            <span class="font-medium">{{ $field }}:</span>
                                            {{ $activity->properties['old'][$field] ?? '—' }} &rarr; {{ $new }}
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </li>
                @empty
                    <p class="text-sm text-gray-400 py-8 text-center">No activity recorded yet.</p>
                @endforelse
            </ul>
        </div>
    </div>
</x-app-layout>
