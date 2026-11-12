<x-app-layout>
    @section('page-title', 'Exit Clearance — ' . $employee->name)

    <div class="max-w-4xl">
        <x-breadcrumb :items="[
            ['label' => 'Employees', 'url' => route('admin.employees.index')],
            ['label' => $employee->name],
            ['label' => 'Exit clearance'],
        ]" class="mb-4" />

        <div class="mb-6">
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Exit clearance — {{ $employee->name }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                Everything this employee still holds. They can be deactivated once every asset has been returned and received.
            </p>
        </div>

        {{-- WorkflowService reports config errors under the "workflow" key, which matches no field. --}}
        @foreach(['workflow', 'asset', 'movement_type_id', 'to_location_id'] as $formError)
            @error($formError)
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 dark:border-red-800/50 dark:bg-red-900/20 p-3 text-sm text-red-700 dark:text-red-400">{{ $message }}</div>
            @enderror
        @endforeach

        @if($assets->isEmpty())
            <div class="rounded-xl border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/20 p-6 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-green-800 dark:text-green-300"><strong>Cleared.</strong> {{ $employee->name }} holds no assets.</p>
                @if($employee->is_active)
                    <form method="POST" action="{{ route('admin.employees.toggle', $employee) }}">
                        @csrf @method('PATCH')
                        <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-green-600 text-white hover:bg-green-700">Deactivate employee</button>
                    </form>
                @else
                    <span class="text-sm text-gray-500">Already inactive.</span>
                @endif
            </div>
        @else
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Asset</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Asset ID</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Location</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Return</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($assets as $asset)
                            <tr>
                                <td class="px-4 py-3">
                                    <a href="{{ route('assets.show', $asset) }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">{{ $asset->name }}</a>
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-400">{{ $asset->asset_tag ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $asset->location?->name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    @php $state = $states[$asset->id]; @endphp
                                    <x-status-badge :color="$state['color']" :label="$state['label']" :dot="false" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($returnable > 0)
                @can('movement.assign')
                    <form method="POST" action="{{ route('admin.employees.clearance.return', $employee) }}"
                          class="mt-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6 space-y-4">
                        @csrf
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Raise a return for the {{ $returnable }} asset(s) still with the employee</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="to_location_id" value="Return to (location)" />
                                <x-searchable-select id="to_location_id" name="to_location_id">
                                    <option value="">— Keep each asset's current location —</option>
                                    @foreach($locations as $location)
                                        <option value="{{ $location->id }}" @selected(old('to_location_id') == $location->id)>{{ $location->name }}</option>
                                    @endforeach
                                </x-searchable-select>
                            </div>
                            <div>
                                <x-input-label for="notes" value="Notes" />
                                <x-text-input id="notes" name="notes" class="mt-1 block w-full" :value="old('notes', 'Exit clearance')" />
                            </div>
                        </div>
                        <p class="text-xs text-gray-400">One approval covers all of them. After approval each asset is In Transit until it is scanned in at the destination.</p>
                        <div class="flex justify-end">
                            <x-primary-button>Raise return for all</x-primary-button>
                        </div>
                    </form>
                @endcan
            @else
                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">Every asset already has a return or another movement in progress. Approve and receive them to finish the clearance.</p>
            @endif
        @endif
    </div>
</x-app-layout>
