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

            @php $replaceViewerId = 'replace-tag-viewer-'.\Illuminate\Support\Str::random(8); @endphp
            <div x-data="tagReplace(@js([
                    'viewerId' => $replaceViewerId,
                    'selectId' => 'new_tag_id',
                    'tags'     => $availableTags->pluck('id', 'tag_number'),
                 ]))">
                <x-input-label for="new_tag_id" value="New Tag (from pool) *" />
                <select id="new_tag_id" name="new_tag_id" required
                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                    <option value="">— Select available tag —</option>
                    @foreach($availableTags as $tag)
                        <option value="{{ $tag->id }}" @selected(old('new_tag_id') == $tag->id)>{{ $tag->tag_number }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('new_tag_id')" class="mt-1" />

                {{-- Scan the replacement tag: hardware barcode reader types into the box (+Enter),
                     or use the phone camera. Both resolve to a pool tag and drive the select above. --}}
                <div class="mt-3">
                    <x-input-label for="new_tag_number" value="Or scan the tag" />
                    <div class="mt-1 flex gap-2">
                        <input id="new_tag_number" type="text" autocomplete="off" @keydown.enter.prevent
                               x-model="scanInput" @input="resolve()"
                               placeholder="Scan or type tag number"
                               class="flex-1 min-w-0 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm font-mono" />
                        <button type="button" @click="toggle()" :disabled="starting"
                                class="flex-shrink-0 inline-flex items-center gap-1.5 px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-60 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <span x-text="active ? 'Stop' : (starting ? 'Starting…' : 'Scan')"></span>
                        </button>
                    </div>
                    <p class="mt-1 text-xs text-gray-400">A hardware barcode reader can scan straight into this box — QR or barcode both work.</p>

                    <p x-show="matchStatus === 'matched'" x-cloak class="mt-1 text-xs text-green-600 dark:text-green-400">
                        Selected pool tag <span class="font-mono" x-text="matchedNumber"></span>.
                    </p>
                    <p x-show="matchStatus === 'notfound'" x-cloak class="mt-1 text-xs text-red-600 dark:text-red-400">
                        That tag isn't an available pool tag.
                    </p>

                    <div x-show="active" x-cloak class="mt-3">
                        <div id="{{ $replaceViewerId }}" class="w-full max-w-sm overflow-hidden rounded-lg bg-black"></div>
                    </div>
                    <div x-show="snapshot" x-cloak class="mt-2">
                        <img :src="snapshot" alt="Scanned frame" class="w-40 rounded-md border border-gray-200 dark:border-gray-700" />
                    </div>
                    <p x-show="error" x-cloak x-text="error"
                       class="mt-2 text-xs text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg px-3 py-2"></p>
                </div>
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
