<x-app-layout>
    @section('page-title', 'Scan Result')

    <div class="max-w-lg">
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-6">
            Tag {{ $result->tag->tag_number }}
        </h1>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            @if($result->status === 'available')
                <p class="text-sm text-gray-700 dark:text-gray-300 mb-4">
                    This tag is <span class="font-medium text-green-600 dark:text-green-400">available</span> and not yet assigned to an asset.
                </p>

                @if($assignableAssets->isNotEmpty())
                    <div x-data="{ assetUrl: '' }">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Assign to an asset</label>
                        <form :action="assetUrl" method="POST" @submit="if (!assetUrl) $event.preventDefault()">
                            @csrf
                            <input type="hidden" name="tag_number" value="{{ $result->tag->tag_number }}">
                            <select x-model="assetUrl" required
                                    class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm mb-3">
                                <option value="">— Select asset —</option>
                                @foreach($assignableAssets as $asset)
                                    <option value="{{ route('assets.tags.assign', $asset) }}">{{ $asset->name }}</option>
                                @endforeach
                            </select>
                            <x-primary-button type="submit">Assign Tag</x-primary-button>
                        </form>
                    </div>
                @else
                    <p class="text-sm text-gray-400">No unassigned assets to attach this tag to right now.</p>
                @endif
            @elseif($result->status === 'inactive')
                <p class="text-sm text-gray-700 dark:text-gray-300 mb-2">
                    This tag has been <span class="font-medium text-gray-500">retired</span> and is no longer in use.
                </p>
                @if($result->currentTag)
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        The asset now uses tag
                        <a href="{{ route('scan.resolve', $result->currentTag->tag_number) }}" class="font-mono text-indigo-600 dark:text-indigo-400 hover:underline">{{ $result->currentTag->tag_number }}</a>.
                    </p>
                @elseif($result->asset)
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        <a href="{{ route('assets.show', $result->asset) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">View {{ $result->asset->name }}</a>
                    </p>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
