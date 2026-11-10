@props(['disabled' => false])

{{-- Password field with a show/hide toggle. Attributes pass straight through to the <input>; `type` is owned by the toggle.
     The wrapper carries the top margin so the toggle button stays vertically centred on the input. --}}
<div x-data="{ show: false }" class="relative mt-1">
    <input @disabled($disabled) :type="show ? 'text' : 'password'" type="password"
        {{ $attributes->except('type')->merge(['class' => 'pe-10 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm']) }}>

    <button type="button" @click="show = !show"
        class="absolute inset-y-0 end-0 flex items-center px-3 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 focus:outline-none focus-visible:text-indigo-600"
        :aria-label="show ? @js(__('Hide password')) : @js(__('Show password'))"
        :aria-pressed="show.toString()"
        @if ($attributes->has('id')) aria-controls="{{ $attributes->get('id') }}" @endif>
        {{-- Eye (password hidden) --}}
        <svg x-show="!show" class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
        </svg>
        {{-- Eye-slash (password visible) --}}
        <svg x-show="show" x-cloak class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
        </svg>
    </button>
</div>
