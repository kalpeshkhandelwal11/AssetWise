<x-app-layout>
    @section('page-title', $cfg['label'])
    @section('breadcrumb')
        <x-breadcrumb :items="[
            ['label' => 'Masters', 'url' => route('admin.masters.landing')],
            ['label' => $cfg['label']],
        ]" />
    @endsection

    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.masters.landing') }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <div>
                <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $cfg['label'] }}</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ $items->total() }} records</p>
            </div>
        </div>

        {{-- Add modal trigger --}}
        <button x-data @click="$dispatch('open-add-modal')"
                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Add
        </button>
    </div>

    {{-- Filters --}}
    <x-filter-bar :clear="route('admin.masters.index', $entity)">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name or code…"
               class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
        <select name="status" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
            <option value="">All</option>
            <option value="active"   @selected(request('status') === 'active')>Active</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
        </select>
    </x-filter-bar>

    <x-data-table :paginator="$items">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Code</th>
                        @if(!empty($cfg['has_color']))
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Color</th>
                        @endif
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($items as $item)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">
                                {{ $item->name }}
                                @if(!empty($cfg['has_system']) && $item->is_system)
                                    <span class="ml-1 text-xs text-gray-400">(system)</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-400">{{ $item->code }}</td>
                            @if(!empty($cfg['has_color']))
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-2">
                                        <span class="w-4 h-4 rounded" style="background-color: {{ $item->color }}"></span>
                                        <span class="font-mono text-xs text-gray-500">{{ $item->color }}</span>
                                    </span>
                                </td>
                            @endif
                            <td class="px-4 py-3">
                                <x-status-badge :color="$item->is_active ? 'green' : 'gray'" :label="$item->is_active ? 'Active' : 'Inactive'" />
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    {{-- Edit (triggers modal via Alpine) --}}
                                    <button x-data
                                            @click="$dispatch('open-edit-modal', {{ json_encode(['id' => $item->id, 'name' => $item->name, 'code' => $item->code, 'color' => $item->color ?? null]) }})"
                                            class="p-1.5 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                            title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>
                                    {{-- Toggle active --}}
                                    <form method="POST" action="{{ route('admin.masters.toggle', [$entity, $item->id]) }}">
                                        @csrf @method('PATCH')
                                        <button type="submit"
                                                class="p-1.5 text-gray-400 hover:text-amber-600 dark:hover:text-amber-400 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                                title="{{ $item->is_active ? 'Deactivate' : 'Activate' }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636"/>
                                            </svg>
                                        </button>
                                    </form>
                                    {{-- Delete --}}
                                    @if(empty($cfg['has_system']) || !$item->is_system)
                                        <x-confirm-modal :action="route('admin.masters.destroy', [$entity, $item->id])"
                                                          title="Delete this record?"
                                                          message="This will permanently remove the record. This action cannot be undone."
                                                          trigger-class="p-1.5 text-gray-400 hover:text-red-600 dark:hover:text-red-400 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                                          trigger-title="Delete">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </x-confirm-modal>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ !empty($cfg['has_color']) ? 5 : 4 }}" class="px-4 py-12 text-center text-sm text-gray-400">No records found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
    </x-data-table>

    {{-- Add / Edit Modal --}}
    <div x-data="{
            showModal: false,
            editId: null,
            form: { name: '', code: '', color: '#6b7280' },
            open(data = null) {
                this.editId = data ? data.id : null;
                this.form = data ? { name: data.name, code: data.code, color: data.color ?? '#6b7280' } : { name: '', code: '', color: '#6b7280' };
                this.showModal = true;
            }
         }"
         @open-add-modal.window="open()"
         @open-edit-modal.window="open($event.detail)"
         x-show="showModal"
         x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4"
         style="display:none">
        <div @click.outside="showModal = false"
             class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4" x-text="editId ? 'Edit Record' : 'New Record'"></h2>

            {{-- Add form --}}
            <template x-if="!editId">
                <form method="POST" action="{{ route('admin.masters.store', $entity) }}" class="space-y-4">
                    @csrf
                    <div>
                        <x-input-label for="modal_name" value="Name *" />
                        <x-text-input id="modal_name" name="name" class="mt-1 block w-full" x-model="form.name" required autofocus />
                    </div>
                    <div>
                        <x-input-label for="modal_code" value="Code *" />
                        <x-text-input id="modal_code" name="code" class="mt-1 block w-full font-mono uppercase" x-model="form.code" required />
                    </div>
                    @if(!empty($cfg['has_color']))
                        <div>
                            <x-input-label for="modal_color" value="Color" />
                            <div class="mt-1 flex items-center gap-3">
                                <input type="color" id="modal_color" name="color" x-model="form.color"
                                       class="h-9 w-16 rounded border border-gray-300 dark:border-gray-700 cursor-pointer">
                                <span class="text-sm font-mono text-gray-500" x-text="form.color"></span>
                            </div>
                        </div>
                    @endif
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="showModal = false"
                                class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 rounded-lg border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            Cancel
                        </button>
                        <x-primary-button>Create</x-primary-button>
                    </div>
                </form>
            </template>

            {{-- Edit form (dynamically targets update route) --}}
            <template x-if="editId">
                <form method="POST" :action="`{{ url('admin/masters/' . $entity) }}/` + editId" class="space-y-4">
                    @csrf @method('PUT')
                    <div>
                        <x-input-label for="modal_edit_name" value="Name *" />
                        <x-text-input id="modal_edit_name" name="name" class="mt-1 block w-full" x-model="form.name" required autofocus />
                    </div>
                    <div>
                        <x-input-label for="modal_edit_code" value="Code *" />
                        <x-text-input id="modal_edit_code" name="code" class="mt-1 block w-full font-mono uppercase" x-model="form.code" required />
                    </div>
                    @if(!empty($cfg['has_color']))
                        <div>
                            <x-input-label for="modal_edit_color" value="Color" />
                            <div class="mt-1 flex items-center gap-3">
                                <input type="color" id="modal_edit_color" name="color" x-model="form.color"
                                       class="h-9 w-16 rounded border border-gray-300 dark:border-gray-700 cursor-pointer">
                                <span class="text-sm font-mono text-gray-500" x-text="form.color"></span>
                            </div>
                        </div>
                    @endif
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="showModal = false"
                                class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 rounded-lg border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            Cancel
                        </button>
                        <x-primary-button>Update</x-primary-button>
                    </div>
                </form>
            </template>
        </div>
    </div>
</x-app-layout>
