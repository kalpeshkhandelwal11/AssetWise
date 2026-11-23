{{-- "Kit" tag: shown when the asset is linked into a kit or bundle (kit_assets). --}}
@foreach($asset->kitAssets->map(fn ($ka) => $ka->kitItem?->kit)->filter()->unique('id') as $kit)
    @php
        $kitLabel = $kit->code ?: $kit->name;
        $kitClass = 'inline-flex items-center gap-1 align-middle ml-1 px-1.5 py-0.5 rounded text-[11px] font-medium bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300';
    @endphp
    @can('kits.manage')
        <a href="{{ route('kits.edit', $kit) }}" @click.stop title="Part of kit {{ $kit->name }}" class="{{ $kitClass }} hover:underline">@include('modules.assets.partials.kit-icon') Kit: {{ $kitLabel }}</a>
    @else
        <span title="Part of kit {{ $kit->name }}" class="{{ $kitClass }}">@include('modules.assets.partials.kit-icon') Kit: {{ $kitLabel }}</span>
    @endcan
@endforeach
@if(isset($bundles) && ($bundle = $bundles->get($asset->id)))
    <a href="{{ route('kit-assignments.show', $bundle) }}" @click.stop title="Assigned as part of an ad-hoc bundle (kit assignment #{{ $bundle->id }})"
       class="inline-flex items-center gap-1 align-middle ml-1 px-1.5 py-0.5 rounded text-[11px] font-medium bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300 hover:underline">@include('modules.assets.partials.kit-icon') Bundle #{{ $bundle->id }}</a>
@endif
