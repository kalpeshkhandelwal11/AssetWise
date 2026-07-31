<x-app-layout>
    @section('page-title', 'Shared Masters')

    <div class="mb-6">
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Shared Masters</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Lookup tables used across asset lifecycle</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($entities as $entity)
            <a href="{{ route('admin.masters.index', $entity['slug']) }}"
               class="group flex items-center justify-between p-5 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm hover:border-indigo-400 dark:hover:border-indigo-500 hover:shadow-md transition-all">
                <div>
                    <p class="font-semibold text-gray-900 dark:text-gray-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                        {{ $entity['label'] }}
                    </p>
                    <p class="text-sm text-gray-400 mt-0.5">{{ $entity['count'] }} record{{ $entity['count'] !== 1 ? 's' : '' }}</p>
                </div>
                <svg class="w-5 h-5 text-gray-300 group-hover:text-indigo-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        @endforeach

        <a href="{{ route('admin.locations.index') }}"
           class="group flex items-center justify-between p-5 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm hover:border-indigo-400 dark:hover:border-indigo-500 hover:shadow-md transition-all">
            <div>
                <p class="font-semibold text-gray-900 dark:text-gray-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                    Locations
                </p>
                <p class="text-sm text-gray-400 mt-0.5">Locations, Buildings, Floors, Rooms</p>
            </div>
            <svg class="w-5 h-5 text-gray-300 group-hover:text-indigo-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
        </a>
    </div>
</x-app-layout>
