<x-app-layout>
    @section('page-title', 'Login History')

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Login History</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">All login attempts across the system</p>
        </div>
    </div>

    {{-- Filters --}}
    <x-filter-bar :clear="route('admin.login-history.index')">
        <select name="status" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
            <option value="">All Statuses</option>
            <option value="success" @selected(request('status') === 'success')>Success</option>
            <option value="failed"  @selected(request('status') === 'failed')>Failed</option>
        </select>
        <input type="date" name="date_from" value="{{ request('date_from') }}"
            class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
        <input type="date" name="date_to" value="{{ request('date_to') }}"
            class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
    </x-filter-bar>

    {{-- Table --}}
    <x-data-table :paginator="$histories">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">User / Email</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">IP Address</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Device</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Time</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($histories as $entry)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ $entry->user?->name ?? '—' }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $entry->email }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <x-status-badge :color="$entry->status === 'success' ? 'green' : 'red'" :label="$entry->status === 'success' ? 'Success' : 'Failed'" />
                                @if($entry->failure_reason)
                                    <p class="text-xs text-gray-400 mt-0.5">{{ $entry->failure_reason }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-400">
                                {{ $entry->ip_address }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="capitalize text-gray-600 dark:text-gray-400">{{ $entry->device_type }}</span>
                            </td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                {{ $entry->created_at->format('d M Y, H:i') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center text-sm text-gray-400">No login records found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
    </x-data-table>
</x-app-layout>
