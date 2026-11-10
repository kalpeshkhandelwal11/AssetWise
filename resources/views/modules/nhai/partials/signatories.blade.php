{{-- Signatory names for the certificate (blank lines are left for wet signatures). Expects $values. --}}
<div>
    <x-input-label value="Signatories (printed on the certificate)" />
    <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-3">
        @foreach(\App\Models\PlazaTakeover::SIGNATORIES as $key => $label)
            <div>
                <label class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</label>
                <x-text-input name="signatories[{{ $key }}]" class="mt-0.5 block w-full" :value="old('signatories.'.$key, $values[$key] ?? '')" placeholder="Name / firm" />
            </div>
        @endforeach
    </div>
</div>
