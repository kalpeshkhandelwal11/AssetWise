{{-- Receipt sign-off: condition + notes + photos. Record-only (never changes asset status). --}}
<div class="space-y-4 border-t border-gray-100 dark:border-gray-700 pt-4 mb-4">
    <div>
        <x-input-label value="Condition on receipt" />
        <div class="mt-1.5 flex flex-wrap gap-4 text-sm">
            @foreach(['ok' => 'Received OK', 'damaged' => 'Damaged', 'missing' => 'Missing / short'] as $val => $lbl)
                <label class="inline-flex items-center gap-1.5 cursor-pointer">
                    <input type="radio" name="verification_condition" value="{{ $val }}"
                           @checked(old('verification_condition') === $val)
                           class="text-indigo-600 focus:ring-indigo-500 border-gray-300 dark:border-gray-600 dark:bg-gray-900">
                    <span class="text-gray-700 dark:text-gray-300">{{ $lbl }}</span>
                </label>
            @endforeach
        </div>
        <x-input-error :messages="$errors->get('verification_condition')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="verification_notes" value="Notes (optional)" />
        <textarea id="verification_notes" name="verification_notes" rows="2"
                  placeholder="e.g. 1 of 2 boxes missing; screen cracked on arrival"
                  class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">{{ old('verification_notes') }}</textarea>
        <x-input-error :messages="$errors->get('verification_notes')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="photos" value="Photos (optional)" />
        <input id="photos" name="photos[]" type="file" accept="image/*" capture="environment" multiple
               class="mt-1 block w-full text-sm text-gray-600 dark:text-gray-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-indigo-900/30 dark:file:text-indigo-300" />
        <p class="mt-1 text-xs text-gray-400">Up to 8 images. On a phone this opens the camera.</p>
        <x-input-error :messages="$errors->get('photos')" class="mt-1" />
        <x-input-error :messages="$errors->get('photos.0')" class="mt-1" />
    </div>
</div>
