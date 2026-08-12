<x-app-layout>
    @section('page-title', 'Notifications')

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Notifications</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Everything sent to you across AssetWise</p>
        </div>
        @if($notifications->getCollection()->contains(fn ($n) => ! $n->read_at))
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                @method('PATCH')
                <x-secondary-button type="submit">Mark all read</x-secondary-button>
            </form>
        @endif
    </div>

    <x-data-table :paginator="$notifications">
        <div class="divide-y divide-gray-100 dark:divide-gray-700/50">
            @forelse($notifications as $notification)
                <form method="POST" action="{{ route('notifications.read', $notification->id) }}" class="block">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="w-full flex items-start gap-3 px-4 py-4 text-left hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors {{ $notification->read_at ? 'opacity-60' : '' }}">
                        <span class="flex-shrink-0 mt-0.5 w-9 h-9 rounded-full bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                            <x-notification-icon :icon="$catalog->icon($notification->type)" class="w-5 h-5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-2">
                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $catalog->title($notification->type, $notification->data) }}</span>
                                <span class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ $catalog->label($notification->type) }}</span>
                            </span>
                            <span class="block text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ $catalog->body($notification->type, $notification->data) }}</span>
                            <span class="block text-xs text-gray-400 dark:text-gray-500 mt-1">{{ $notification->created_at->format('d M Y, H:i') }}</span>
                        </span>
                        @if(!$notification->read_at)
                            <span class="flex-shrink-0 mt-2 w-2 h-2 rounded-full bg-indigo-500"></span>
                        @endif
                    </button>
                </form>
            @empty
                <p class="px-4 py-10 text-center text-sm text-gray-400">No notifications yet</p>
            @endforelse
        </div>
    </x-data-table>
</x-app-layout>
