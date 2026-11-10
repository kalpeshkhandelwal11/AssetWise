<x-app-layout>
    @section('page-title', 'Activity Log')

    <div class="mb-6">
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Activity Log</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Audit trail of changes across the system</p>
    </div>

    {{-- Filters --}}
    <x-filter-bar :clear="route('admin.activity-log.index')">
        <select name="log_name" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
            <option value="">All Logs</option>
            @foreach($logNames as $name)
                <option value="{{ $name }}" @selected(request('log_name') === $name)>{{ ucfirst($name) }}</option>
            @endforeach
        </select>
        <select name="event" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
            <option value="">All Events</option>
            <option value="created" @selected(request('event') === 'created')>Created</option>
            <option value="updated" @selected(request('event') === 'updated')>Updated</option>
            <option value="deleted" @selected(request('event') === 'deleted')>Deleted</option>
        </select>
        <select name="causer_id" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
            <option value="">All Users</option>
            @foreach($causers as $causer)
                <option value="{{ $causer->id }}" @selected((string) request('causer_id') === (string) $causer->id)>{{ $causer->name }}</option>
            @endforeach
        </select>
        <input type="date" name="date_from" value="{{ request('date_from') }}"
               class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
        <input type="date" name="date_to" value="{{ request('date_to') }}"
               class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
    </x-filter-bar>

    <x-data-table :paginator="$activities">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">When</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Log</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Event</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Subject</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Causer</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Description</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Details</th>
                    </tr>
                </thead>
                @forelse($activities as $activity)
                    <tbody x-data="{ open: false }" class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors align-top">
                            <td class="px-4 py-3 whitespace-nowrap text-gray-500 dark:text-gray-400">{{ $activity->created_at->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3">
                                <x-status-badge color="indigo" :label="$activity->log_name" :dot="false" />
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $activity->event }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                @if($activity->subject_type)
                                    {{ \App\Support\ActivityAttributePresenter::subjectLabel($activity) }}
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $activity->causer?->name ?? 'System' }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $activity->description }}</td>
                            <td class="px-4 py-3 text-right">
                                @if($activity->properties->isNotEmpty())
                                    <button @click="open = !open" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
                                        <span x-text="open ? 'Hide' : 'View'"></span>
                                    </button>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                        </tr>
                        @if($activity->properties->isNotEmpty())
                            <tr x-show="open" x-cloak class="bg-gray-50 dark:bg-gray-900/30">
                                <td colspan="7" class="px-4 py-3">
                                    @php $diffRows = \App\Support\ActivityAttributePresenter::diff($activity); @endphp
                                    @if($diffRows)
                                        <table class="text-xs w-full max-w-3xl">
                                            <thead>
                                                <tr class="text-left text-gray-400">
                                                    <th class="py-1 pr-4 font-medium">Field</th>
                                                    @if($activity->event !== 'created')<th class="py-1 pr-4 font-medium">Old</th>@endif
                                                    @if($activity->event !== 'deleted')<th class="py-1 font-medium">New</th>@endif
                                                </tr>
                                            </thead>
                                            <tbody class="text-gray-600 dark:text-gray-300">
                                                @foreach($diffRows as $row)
                                                    <tr class="border-t border-gray-200 dark:border-gray-700">
                                                        <td class="py-1 pr-4 font-medium">{{ $row['label'] }}</td>
                                                        @if($activity->event !== 'created')<td class="py-1 pr-4 text-gray-500 dark:text-gray-400">{{ $row['old'] }}</td>@endif
                                                        @if($activity->event !== 'deleted')<td class="py-1">{{ $row['new'] }}</td>@endif
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    @endif
                                    @php $extra = collect($activity->properties)->except(['attributes', 'old']); @endphp
                                    @if($extra->isNotEmpty())
                                        <dl class="mt-2 text-xs text-gray-500 dark:text-gray-400 space-y-0.5">
                                            @foreach($extra as $key => $value)
                                                <div><dt class="inline font-medium">{{ \Illuminate\Support\Str::headline($key) }}:</dt>
                                                    <dd class="inline">{{ \App\Support\ActivityAttributePresenter::value($key, $value) }}</dd></div>
                                            @endforeach
                                        </dl>
                                    @endif
                                    @role('Super Admin')
                                        <details class="mt-2">
                                            <summary class="text-xs text-gray-400 cursor-pointer">Raw</summary>
                                            <pre class="text-xs text-gray-500 dark:text-gray-400 whitespace-pre-wrap break-all">{{ json_encode($activity->properties, JSON_PRETTY_PRINT) }}</pre>
                                        </details>
                                    @endrole
                                </td>
                            </tr>
                        @endif
                    </tbody>
                @empty
                    <tbody>
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-sm text-gray-400">No activity recorded.</td>
                        </tr>
                    </tbody>
                @endforelse
            </table>
    </x-data-table>
</x-app-layout>
