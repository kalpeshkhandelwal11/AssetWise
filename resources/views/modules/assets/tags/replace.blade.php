<x-app-layout>
    @section('page-title', 'Replace Tag')

    <div class="max-w-lg">
        <div class="flex items-center gap-3 mb-6">
            <a href="{{ route('assets.show', $asset) }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <div>
                <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Replace Tag</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ $asset->name }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route('assets.tags.replace.submit', $asset) }}"
              class="space-y-5 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            @csrf

            <p class="text-sm text-gray-500 dark:text-gray-400">
                Current tag:
                <span class="font-mono font-medium text-gray-900 dark:text-gray-100">{{ $asset->activeTag()?->tag_number ?? '—' }}</span>.
                This request goes through the tag replacement approval chain — the old tag stays
                active and scannable until every approval level signs off.
            </p>

            <div>
                <x-input-label for="new_tag_id" value="New Tag (from pool) *" />
                <select id="new_tag_id" name="new_tag_id" required
                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                    <option value="">— Select available tag —</option>
                    @foreach($availableTags as $tag)
                        <option value="{{ $tag->id }}" @selected(old('new_tag_id') == $tag->id)>{{ $tag->tag_number }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('new_tag_id')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="reason" value="Reason *" />
                <textarea id="reason" name="reason" rows="3" required
                          class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">{{ old('reason') }}</textarea>
                <x-input-error :messages="$errors->get('reason')" class="mt-1" />
            </div>

            <x-input-error :messages="$errors->get('asset')" class="mt-1" />
            <x-input-error :messages="$errors->get('workflow')" class="mt-1" />

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                <a href="{{ route('assets.show', $asset) }}"
                   class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-lg border border-gray-300 dark:border-gray-700 transition-colors">
                    Cancel
                </a>
                <x-primary-button>Submit for Approval</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
