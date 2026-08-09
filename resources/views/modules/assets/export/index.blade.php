<x-app-layout>
    @section('page-title', 'Export Assets')

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Export Assets</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Filter, then export — matching rows only. Large exports are emailed as a download link instead of downloading immediately.</p>
        </div>
        <a href="{{ route('assets.index') }}" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-lg border border-gray-300 dark:border-gray-700 transition-colors">
            Back to Assets
        </a>
    </div>

    @if(session('success'))
        <div class="mb-6 p-3 rounded-lg bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-5">
        <form method="POST" action="{{ route('assets.export.store') }}" class="flex flex-wrap gap-3">
            @csrf
            <input type="text" name="search" value="{{ old('search') }}" placeholder="Search tag, name, serial…"
                   class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">

            <select name="company_id" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                <option value="">All Companies</option>
                @foreach($companies as $company)
                    <option value="{{ $company->id }}" @selected(old('company_id') == $company->id)>{{ $company->name }}</option>
                @endforeach
            </select>

            <select name="category_id" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                <option value="">All Categories</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>

            <select name="asset_type_id" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                <option value="">All Types</option>
                @foreach($types as $type)
                    <option value="{{ $type->id }}" @selected(old('asset_type_id') == $type->id)>{{ $type->name }}</option>
                @endforeach
            </select>

            <select name="status_id" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                <option value="">All Statuses</option>
                @foreach($statuses as $status)
                    <option value="{{ $status->id }}" @selected(old('status_id') == $status->id)>{{ $status->name }}</option>
                @endforeach
            </select>

            <label class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 px-2">
                From <input type="date" name="date_from" value="{{ old('date_from') }}" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
            </label>
            <label class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 px-2">
                To <input type="date" name="date_to" value="{{ old('date_to') }}" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
            </label>

            <button type="submit" class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                Export
            </button>
        </form>
    </div>
</x-app-layout>
