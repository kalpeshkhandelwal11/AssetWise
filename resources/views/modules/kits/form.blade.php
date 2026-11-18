<x-app-layout>
    @section('page-title', $kit->exists ? 'Edit Kit' : 'New Kit')

    <div class="max-w-3xl space-y-6">
        <x-breadcrumb :items="[
            ['label' => 'Kits', 'url' => route('kits.index')],
            ['label' => $kit->exists ? $kit->name : 'New Kit'],
        ]" class="mb-4" />

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            <form method="POST" action="{{ $kit->exists ? route('kits.update', $kit) : route('kits.store') }}" class="space-y-5">
                @csrf
                @if($kit->exists) @method('PUT') @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <x-input-label for="name" value="Name" />
                        <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $kit->name)" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="code" value="Code" />
                        <x-text-input id="code" name="code" class="mt-1 block w-full" :value="old('code', $kit->code)" required />
                        <x-input-error :messages="$errors->get('code')" class="mt-1" />
                    </div>
                </div>
                <div>
                    <x-input-label for="description" value="Description" />
                    <textarea id="description" name="description" rows="2"
                              class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">{{ old('description', $kit->description) }}</textarea>
                </div>
                <label class="flex items-center gap-2">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $kit->is_active ?? true))
                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="text-sm text-gray-700 dark:text-gray-300">Active</span>
                </label>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                    <a href="{{ route('kits.index') }}"
                       class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 rounded-lg border border-gray-300 dark:border-gray-700">Cancel</a>
                    <x-primary-button>{{ $kit->exists ? 'Save Kit' : 'Create Kit' }}</x-primary-button>
                </div>
            </form>
        </div>

        @if($kit->exists)
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Slots</p>
                    <x-status-badge :color="$isReady ? 'green' : 'amber'" :label="$isReady ? 'Ready to assign' : 'Incomplete'" />
                </div>
                {{-- A refused link (wrong category, already in this kit) is reported here. --}}
                @error('asset_id')
                    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 dark:border-red-800/50 dark:bg-red-900/20 p-3 text-sm text-red-700 dark:text-red-400">{{ $message }}</div>
                @enderror

                <div class="space-y-4">
                    @foreach($kit->items as $item)
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $item->label }}</span>
                                    <span class="text-xs text-gray-400 ml-2">
                                        {{ $item->category?->name ?? 'Any category' }} · needs {{ $item->quantity }} ·
                                        {{ $item->kitAssets->count() }}/{{ $item->quantity }} filled
                                    </span>
                                </div>
                                <form method="POST" action="{{ route('kits.items.destroy', [$kit, $item]) }}">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-red-600 hover:underline">Remove slot</button>
                                </form>
                            </div>

                            <div class="mt-3 space-y-1">
                                @foreach($item->kitAssets as $kitAsset)
                                    <div class="flex items-center justify-between text-sm">
                                        @if($kitAsset->asset)
                                            <a href="{{ route('assets.show', $kitAsset->asset) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                                <span class="font-mono text-xs">{{ $kitAsset->asset->asset_tag }}</span> — {{ $kitAsset->asset->name }}
                                            </a>
                                        @else
                                            <span class="text-gray-400">{{ \App\Models\Asset::labelFor(null) }}</span>
                                        @endif
                                        <form method="POST" action="{{ route('kits.items.assets.destroy', [$kit, $item, $kitAsset]) }}">
                                            @csrf @method('DELETE')
                                            <button class="text-xs text-gray-400 hover:text-red-600">Unlink</button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>

                            @php
                                // Only assets this slot can take: its category (when set), and not
                                // already anywhere in this kit.
                                $inThisKit = $kit->items->flatMap->kitAssets->pluck('asset_id')->all();
                                $slotChoices = $assets
                                    ->when($item->category_id, fn ($c) => $c->where('category_id', $item->category_id))
                                    ->whereNotIn('id', $inThisKit);
                            @endphp
                            @if($slotChoices->isEmpty())
                                <p class="mt-3 text-xs text-gray-400">No more {{ $item->category?->name ?? '' }} assets available to link — every match is already in this kit.</p>
                            @else
                                <form method="POST" action="{{ route('kits.items.assets.store', [$kit, $item]) }}" class="mt-3 flex gap-2" x-data>
                                    @csrf
                                    <div class="flex-1">
                                        <x-searchable-select name="asset_id" required data-placeholder="— Link an asset (type ID or name) —">
                                            <option value="">— Link an asset (type ID or name) —</option>
                                            @foreach($slotChoices as $asset)
                                                <option value="{{ $asset->id }}">{{ $asset->asset_tag ? $asset->asset_tag." — " : "" }}{{ $asset->name }}</option>
                                            @endforeach
                                        </x-searchable-select>
                                    </div>
                                    <button class="px-3 py-1.5 text-sm bg-gray-700 text-white rounded-md hover:bg-gray-800 self-start mt-1">Link</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                    @if($kit->items->isEmpty())
                        <p class="text-sm text-gray-400">No slots yet — add one below.</p>
                    @endif
                </div>

                <form method="POST" action="{{ route('kits.items.store', $kit) }}" class="mt-5 grid grid-cols-1 sm:grid-cols-4 gap-3 items-end border-t border-gray-100 dark:border-gray-700 pt-4">
                    @csrf
                    <div class="sm:col-span-2">
                        <x-input-label for="label" value="Slot label" />
                        <x-text-input id="label" name="label" class="mt-1 block w-full" required />
                    </div>
                    <div>
                        <x-input-label for="category_id" value="Category" />
                        <select id="category_id" name="category_id" class="mt-1 block w-full text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                            <option value="">Any</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex gap-2 items-end">
                        <div class="flex-1">
                            <x-input-label for="quantity" value="Qty" />
                            <x-text-input id="quantity" name="quantity" type="number" min="1" value="1" class="mt-1 block w-full" required />
                        </div>
                        <button class="px-3 py-2 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Add</button>
                    </div>
                    <x-input-error :messages="$errors->get('label')" class="mt-1 sm:col-span-4" />
                </form>
            </div>
        @endif
    </div>
</x-app-layout>
