<x-app-layout>
    @section('page-title', $category->exists ? 'Edit Category' : 'New Category')

    <div class="max-w-2xl">
        <div class="flex items-center gap-3 mb-6">
            <a href="{{ route('admin.categories.index') }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">
                {{ $category->exists ? 'Edit Category' : 'New Category' }}
            </h1>
        </div>

        <form method="POST"
              action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}"
              class="space-y-5 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            @csrf
            @if($category->exists) @method('PUT') @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="name" value="Category Name *" />
                    <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $category->name)" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="code" value="Code *" />
                    <x-text-input id="code" name="code" class="mt-1 block w-full font-mono uppercase"
                                  :value="old('code', $category->code)" placeholder="e.g. LAPTOPS" required />
                    <x-input-error :messages="$errors->get('code')" class="mt-1" />
                </div>
            </div>

            <div>
                <x-input-label for="parent_id" value="Parent Category" />
                <x-searchable-select id="parent_id" name="parent_id">
                    <option value="">— None (top-level) —</option>
                    @foreach($parents as $parent)
                        <option value="{{ $parent->id }}" @selected(old('parent_id', $category->parent_id) == $parent->id)>{{ $parent->name }}</option>
                    @endforeach
                </x-searchable-select>
                <x-input-error :messages="$errors->get('parent_id')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="description" value="Description" />
                <textarea id="description" name="description" rows="2"
                          class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">{{ old('description', $category->description) }}</textarea>
                <x-input-error :messages="$errors->get('description')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="sort_order" value="Sort Order" />
                <x-text-input id="sort_order" type="number" name="sort_order" class="mt-1 block w-32" :value="old('sort_order', $category->sort_order ?? 0)" />
                <x-input-error :messages="$errors->get('sort_order')" class="mt-1" />
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                <a href="{{ route('admin.categories.index') }}"
                   class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-lg border border-gray-300 dark:border-gray-700 transition-colors">
                    Cancel
                </a>
                <x-primary-button>
                    {{ $category->exists ? 'Update Category' : 'Create Category' }}
                </x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
