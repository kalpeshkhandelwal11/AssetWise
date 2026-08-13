{{--
    Searchable dropdown. Drop-in replacement for a native <select>: forwards every attribute
    (id, name, x-model, @change, :disabled, required, …) through $attributes, so it behaves
    exactly like the <select> it replaces but is enhanced into a Tom Select by the `x-searchable`
    Alpine directive (resources/js/searchable-select.js). Options go in the slot as usual.

    Usage:
        <x-searchable-select name="company_id" x-model="companyId" required>
            <option value="">— Select —</option>
            @foreach($companies as $c) <option value="{{ $c->id }}">{{ $c->name }}</option> @endforeach
        </x-searchable-select>
--}}
<select
    x-searchable
    {{ $attributes->merge([
        'class' => 'mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm',
    ]) }}
>
    {{ $slot }}
</select>
