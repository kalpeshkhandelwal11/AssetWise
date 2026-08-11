<x-app-layout>
    @section('page-title', 'Depreciation Methods')

    <div class="max-w-3xl">
        <x-breadcrumb :items="[
            ['label' => 'Administration'],
            ['label' => 'Depreciation Methods'],
        ]" class="mb-4" />

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Method</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Code</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($methods as $method)
                        <tr>
                            <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ $method->name }}</td>
                            <td class="px-4 py-3 text-gray-500 font-mono text-xs">{{ $method->code }}</td>
                            <td class="px-4 py-3">
                                <x-status-badge :color="$method->is_active ? 'green' : 'gray'" :label="$method->is_active ? 'Active' : 'Inactive'" />
                            </td>
                            <td class="px-4 py-3 text-right">
                                <form method="POST" action="{{ route('admin.depreciation-methods.toggle', $method) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
                                        {{ $method->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-400 mt-3">Only active methods are selectable on category and asset depreciation forms. Straight Line is the MVP default.</p>
    </div>
</x-app-layout>
