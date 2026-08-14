<x-app-layout>
    @section('page-title', 'Bulk Import')

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Bulk Import</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Download a category template, fill it in, and upload it back.</p>
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

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        {{-- Step 1: download template --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-5">
            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-1">1. Download a template</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">The template's columns match the chosen category's custom fields exactly, including inherited ones. It includes one <span class="font-medium">sample row from an existing asset</span> as a reference — <span class="font-medium">delete that row before uploading</span>.</p>
            <form method="GET" id="template-form" class="flex gap-2">
                <select name="category_id_select" id="template-category" required
                        class="flex-1 text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                    <option value="">Select category…</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors whitespace-nowrap">
                    Download
                </button>
            </form>
            <script>
                document.getElementById('template-form').addEventListener('submit', function (e) {
                    e.preventDefault();
                    var id = document.getElementById('template-category').value;
                    if (!id) return;
                    window.location = '{{ url('/assets/import/template') }}/' + id;
                });
            </script>
        </div>

        {{-- Step 2: upload --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-5">
            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-1">2. Upload the filled-in file</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Valid rows create assets immediately; invalid rows are reported without blocking the rest of the batch.</p>

            @if($errors->any())
                <div class="mb-3 p-3 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 text-sm">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('assets.import.store') }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <select name="category_id" required
                        class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                    <option value="">Select category…</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                <input type="file" name="file" required accept=".xlsx,.xls,.csv"
                       class="w-full text-sm text-gray-600 dark:text-gray-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-700 dark:file:bg-indigo-900/30 dark:file:text-indigo-300">
                <button type="submit" class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                    Upload &amp; Import
                </button>
            </form>
        </div>
    </div>

    {{-- Batch history --}}
    <x-data-table :paginator="$batches">
        <x-slot:header>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Import history</p>
        </x-slot:header>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">File</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Category</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Success / Error</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Uploaded by</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Report</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($batches as $batch)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                            <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ $batch->filename }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $batch->category?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @php
                                    $batchColor = match($batch->status) {
                                        'completed' => 'green',
                                        'failed'    => 'red',
                                        default     => 'amber',
                                    };
                                @endphp
                                <x-status-badge :color="$batchColor" :label="ucfirst($batch->status)" :dot="false" />
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $batch->success_count }} / {{ $batch->error_count }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $batch->user?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('assets.import.show', $batch) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-12 text-center text-sm text-gray-400">No imports yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
    </x-data-table>
</x-app-layout>
