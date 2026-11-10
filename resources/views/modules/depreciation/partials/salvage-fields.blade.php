{{--
    Salvage (residual) value — mandatory: exactly one of a percentage of cost or a fixed
    amount. Only the active input carries a name, so the other is never submitted (the
    controllers enforce required_without + prohibits).

    Params:
      $salvagePercent  stored/old percent (null when fixed)
      $salvageValue    stored/old fixed amount (null when percent)
      $costInput       optional id of a cost-basis input, for the live "depreciable amount"
      $costValue       optional static cost (category form has no cost input)
--}}
@php
    $defaultPercent = (float) \App\Models\Setting::get('depreciation_default_salvage_percent', '5');
    $oldPercent = old('salvage_percent', $salvagePercent);
    $oldValue = old('salvage_value', $salvageValue);
    $mode = filled($oldValue) && blank($oldPercent) ? 'fixed' : 'percent';
    $percentPrefill = filled($oldPercent) ? (float) $oldPercent : ($mode === 'percent' ? $defaultPercent : null);
@endphp
<div class="sm:col-span-2" x-data="{
        mode: @js($mode),
        percent: @js($percentPrefill),
        amount: @js(filled($oldValue) ? (float) $oldValue : null),
        cost: @js(isset($costValue) ? (float) $costValue : null),
        costInput: @js($costInput ?? null),
        readCost() {
            if (! this.costInput) return this.cost;
            const el = document.getElementById(this.costInput);
            return el && el.value !== '' ? parseFloat(el.value) : null;
        },
        salvage() {
            const cost = this.readCost();
            if (this.mode === 'fixed') return this.amount === null || this.amount === '' ? null : parseFloat(this.amount);
            if (cost === null || this.percent === null || this.percent === '') return null;
            return cost * parseFloat(this.percent) / 100;
        },
        depreciable() {
            const cost = this.readCost(), s = this.salvage();
            return cost === null || s === null ? null : Math.max(0, cost - s);
        },
        fmt(n) { return n === null || isNaN(n) ? '—' : n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
        tick: 0,
    }" x-init="if (costInput) { const el = document.getElementById(costInput); if (el) el.addEventListener('input', () => tick++); }">
    <x-input-label value="Salvage / Residual Value *" />
    <div class="mt-2 flex flex-wrap gap-4 text-sm text-gray-700 dark:text-gray-300">
        <label class="flex items-center gap-2">
            <input type="radio" value="percent" x-model="mode" class="border-gray-300 text-indigo-600 focus:ring-indigo-500">
            Percentage of cost
        </label>
        <label class="flex items-center gap-2">
            <input type="radio" value="fixed" x-model="mode" class="border-gray-300 text-indigo-600 focus:ring-indigo-500">
            Fixed amount
        </label>
    </div>
    <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div x-show="mode === 'percent'">
            <x-text-input id="salvage_percent" type="number" step="0.0001" min="0" max="100" class="block w-full"
                          x-model="percent" x-bind:name="mode === 'percent' ? 'salvage_percent' : null"
                          x-bind:required="mode === 'percent'" placeholder="e.g. 5" />
        </div>
        <div x-show="mode === 'fixed'" x-cloak>
            <x-text-input id="salvage_value" type="number" step="0.01" min="0" class="block w-full"
                          x-model="amount" x-bind:name="mode === 'fixed' ? 'salvage_value' : null"
                          x-bind:required="mode === 'fixed'" placeholder="Amount" />
        </div>
        {{-- tick makes these re-evaluate when the (non-Alpine) cost input changes --}}
        <p class="text-xs text-gray-500 dark:text-gray-400 self-center" x-show="tick >= 0 && readCost() !== null">
            Depreciable amount = cost − salvage = <span class="font-medium text-gray-700 dark:text-gray-300" x-text="tick >= 0 ? fmt(depreciable()) : ''"></span>
        </p>
    </div>
    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
        The salvage portion is excluded from depreciation; the asset is written down to this value, never below it.
    </p>
    <x-input-error :messages="$errors->get('salvage_percent')" class="mt-1" />
    <x-input-error :messages="$errors->get('salvage_value')" class="mt-1" />
</div>
