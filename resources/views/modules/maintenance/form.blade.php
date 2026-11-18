<x-app-layout>
    @section('page-title', $record->exists ? 'Edit Maintenance Record' : 'Log Maintenance')

    <div class="max-w-2xl">
        <x-breadcrumb :items="[
            ['label' => $asset->name, 'url' => route('assets.show', $asset)],
            ['label' => $record->exists ? 'Edit Maintenance Record' : 'Log Maintenance'],
        ]" class="mb-4" />

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            <form method="POST" action="{{ $record->exists ? route('assets.maintenance.update', [$asset, $record]) : route('assets.maintenance.store', $asset) }}" class="space-y-5">
                @csrf
                @if($record->exists) @method('PUT') @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <x-input-label for="maintenance_type_id" value="Maintenance Type" />
                        <select id="maintenance_type_id" name="maintenance_type_id" required
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            <option value="">— Select —</option>
                            @foreach($maintenanceTypes as $type)
                                <option value="{{ $type->id }}" @selected(old('maintenance_type_id', $record->maintenance_type_id) == $type->id)>{{ $type->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('maintenance_type_id')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="status" value="Status" />
                        <select id="status" name="status" required
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            @foreach(['scheduled' => 'Scheduled', 'in_progress' => 'In Progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $record->status ?? 'scheduled') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('status')" class="mt-1" />
                        @if(! $record->exists || $record->status !== 'in_progress')
                            <p class="text-xs text-gray-400 mt-1">Setting "In Progress" flips the asset's status to "In Maintenance" until this record is completed or cancelled.</p>
                        @endif
                    </div>
                    <div>
                        <x-input-label for="scheduled_date" value="Scheduled Date" />
                        <x-text-input id="scheduled_date" type="date" name="scheduled_date" class="mt-1 block w-full" :value="old('scheduled_date', optional($record->scheduled_date)->format('Y-m-d'))" />
                        <x-input-error :messages="$errors->get('scheduled_date')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="repeat_every_months" value="Repeat every (months)" />
                        <x-text-input id="repeat_every_months" type="number" min="1" max="120" name="repeat_every_months" class="mt-1 block w-full" :value="old('repeat_every_months', $record->repeat_every_months)" placeholder="Leave blank for one-off work" />
                        <p class="mt-1 text-xs text-gray-400">When this is completed, the next one is scheduled automatically.</p>
                        <x-input-error :messages="$errors->get('repeat_every_months')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="performed_date" value="Performed Date" />
                        <x-text-input id="performed_date" type="date" name="performed_date" class="mt-1 block w-full" :value="old('performed_date', optional($record->performed_date)->format('Y-m-d'))" />
                        <x-input-error :messages="$errors->get('performed_date')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="vendor" value="Vendor" />
                        <x-text-input id="vendor" name="vendor" class="mt-1 block w-full" :value="old('vendor', $record->vendor)" />
                        <x-input-error :messages="$errors->get('vendor')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="cost" value="Cost" />
                        <x-text-input id="cost" type="number" step="0.01" min="0" name="cost" class="mt-1 block w-full" :value="old('cost', $record->cost)" />
                        <x-input-error :messages="$errors->get('cost')" class="mt-1" />
                    </div>
                </div>

                <div>
                    <x-input-label for="description" value="Description" />
                    <textarea id="description" name="description" rows="3"
                              class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">{{ old('description', $record->description) }}</textarea>
                    <x-input-error :messages="$errors->get('description')" class="mt-1" />
                </div>

                {{-- Capitalisation: a repair that adds value or life goes onto the asset's book value
                     and is depreciated with it, via the depreciation approval. --}}
                <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
                    @foreach(['workflow', 'asset'] as $formError)
                        @error($formError)
                            <div class="mb-3 rounded-lg border border-red-200 bg-red-50 dark:border-red-800/50 dark:bg-red-900/20 p-3 text-sm text-red-700 dark:text-red-400">{{ $message }}</div>
                        @enderror
                    @endforeach

                    @php $capStatus = $record->exists ? $record->capitalizationStatus() : null; @endphp
                    @if($capStatus === 'pending' || $capStatus === 'applied')
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Capitalised</p>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            ₹ {{ number_format((float) $record->capitalized_amount, 2) }} added to the book value
                            @if((int) $record->additional_useful_life_months > 0), life +{{ $record->additional_useful_life_months }} month(s)@endif —
                            {{ $capStatus === 'pending' ? 'waiting for depreciation approval.' : 'approved and in the depreciation schedule.' }}
                        </p>
                    @elseif(! ($canCapitalize ?? false))
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Capitalise this cost</p>
                        <p class="mt-1 text-xs text-gray-400">Not available: this asset is not being depreciated, so there is no book value to add the cost to.</p>
                    @else
                        <div x-data="{ on: {{ old('is_capitalized', $record->is_capitalized) ? 'true' : 'false' }} }">
                            <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                <input type="hidden" name="is_capitalized" value="0">
                                <input type="checkbox" name="is_capitalized" value="1" x-model="on" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                Capitalise this cost (adds value or extends the life)
                            </label>
                            @if($capStatus === 'rejected')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">The last capitalisation was rejected. Save again to resubmit it.</p>
                            @endif
                            <x-input-error :messages="$errors->get('is_capitalized')" class="mt-1" />

                            <div x-show="on" x-cloak class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <div>
                                    <x-input-label for="capitalized_amount" value="Amount to capitalise *" />
                                    <x-text-input id="capitalized_amount" type="number" step="0.01" min="0.01" name="capitalized_amount" class="mt-1 block w-full" :value="old('capitalized_amount', $record->capitalized_amount)" />
                                    <p class="mt-1 text-xs text-gray-400">Up to the cost above. The rest is treated as an expense.</p>
                                    <x-input-error :messages="$errors->get('capitalized_amount')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label for="additional_useful_life_months" value="Extra useful life (months)" />
                                    <x-text-input id="additional_useful_life_months" type="number" min="0" max="600" name="additional_useful_life_months" class="mt-1 block w-full" :value="old('additional_useful_life_months', $record->additional_useful_life_months ?? 0)" />
                                    <p class="mt-1 text-xs text-gray-400">0 if the work does not extend the life.</p>
                                    <x-input-error :messages="$errors->get('additional_useful_life_months')" class="mt-1" />
                                </div>
                                <p class="sm:col-span-2 text-xs text-gray-500 dark:text-gray-400">
                                    Applies when the record is <strong>Completed</strong> and goes for depreciation approval.
                                    Once approved, from the performed date the new book value (current value + this amount)
                                    is depreciated over the remaining life plus any extra months.
                                </p>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                    <a href="{{ route('assets.show', $asset) }}"
                       class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-lg border border-gray-300 dark:border-gray-700 transition-colors">
                        Cancel
                    </a>
                    <x-primary-button>{{ $record->exists ? 'Update Record' : 'Log Maintenance' }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
