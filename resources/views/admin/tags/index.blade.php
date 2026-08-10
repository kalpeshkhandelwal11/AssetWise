<x-app-layout>
    @section('page-title', 'Tag Pool')

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Tag Pool</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Pre-generated QR/barcode tags awaiting assignment</p>
        </div>
        <div class="flex items-center gap-2">
            @can('tags.print')
                <a href="{{ route('admin.tags.print.pdf') }}"
                   class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-lg border border-gray-300 dark:border-gray-700 transition-colors">
                    Print Available (PDF)
                </a>
                <a href="{{ route('admin.tags.print.word') }}"
                   class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-lg border border-gray-300 dark:border-gray-700 transition-colors">
                    Print Available (Word)
                </a>
            @endcan
            @can('tags.generate')
            <a href="{{ route('admin.tags.batches.create') }}"
               class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Generate Batch
            </a>
            @endcan
        </div>
    </div>

    <x-filter-bar :clear="route('admin.tags.index')">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search tag number…"
               class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">

        <select name="status" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
            <option value="">All Statuses</option>
            @foreach(['available', 'assigned', 'inactive'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
    </x-filter-bar>

    <x-data-table :paginator="$tags">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tag Number</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Batch</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($tags as $tag)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                            <td class="px-4 py-3 font-mono text-gray-900 dark:text-gray-100">{{ $tag->tag_number }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ ucfirst($tag->code_type) }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">#{{ $tag->batch_id }}</td>
                            <td class="px-4 py-3">
                                @php
                                    $badgeColor = match($tag->status) {
                                        'available' => 'green',
                                        'assigned' => 'indigo',
                                        default => 'gray',
                                    };
                                @endphp
                                <x-status-badge :color="$badgeColor" :label="ucfirst($tag->status)" :dot="false" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-12 text-center text-sm text-gray-400">No tags found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
    </x-data-table>
</x-app-layout>
