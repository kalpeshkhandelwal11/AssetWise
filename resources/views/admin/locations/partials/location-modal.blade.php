<div x-data="{
        showModal: false,
        editId: null,
        form: { name: '', code: '', address: '', manager_employee_id: '' },
        open(data = null) {
            this.editId = data ? data.id : null;
            this.form = data
                ? { name: data.name, code: data.code, address: data.address ?? '', manager_employee_id: data.manager_employee_id ? String(data.manager_employee_id) : '' }
                : { name: '', code: '', address: '', manager_employee_id: '' };
            this.showModal = true;
        }
     }"
     @open-location-modal.window="open($event.detail ?? null)"
     x-show="showModal"
     x-transition.opacity
     class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4"
     style="display:none">
    <div @click.outside="showModal = false"
         class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4" x-text="editId ? 'Edit Location' : 'New Location'"></h2>

        <template x-if="!editId">
            <form method="POST" action="{{ route('admin.locations.store') }}" class="space-y-4">
                @csrf
                <div>
                    <x-input-label value="Name *" />
                    <x-text-input name="name" class="mt-1 block w-full" x-model="form.name" required autofocus />
                </div>
                <div>
                    <x-input-label value="Code *" />
                    <x-text-input name="code" class="mt-1 block w-full font-mono uppercase" x-model="form.code" placeholder="e.g. HQ" required />
                </div>
                <div>
                    <x-input-label value="Address" />
                    <textarea name="address" rows="2" x-model="form.address"
                              class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"></textarea>
                </div>
                <div>
                    <x-input-label value="Location manager" />
                    <select name="manager_employee_id" x-model="form.manager_employee_id"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                        <option value="">— None —</option>
                        @foreach($managers as $manager)
                            <option value="{{ $manager->id }}">{{ $manager->name }} ({{ $manager->employee_code }}){{ $manager->user_id ? '' : ' — no login' }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-400">Scans in assets transferred to this location (needs a linked login).</p>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="showModal = false" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 rounded-lg border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">Cancel</button>
                    <x-primary-button>Create</x-primary-button>
                </div>
            </form>
        </template>

        <template x-if="editId">
            <form method="POST" :action="`{{ url('admin/locations') }}/` + editId" class="space-y-4">
                @csrf @method('PUT')
                <div>
                    <x-input-label value="Name *" />
                    <x-text-input name="name" class="mt-1 block w-full" x-model="form.name" required autofocus />
                </div>
                <div>
                    <x-input-label value="Code *" />
                    <x-text-input name="code" class="mt-1 block w-full font-mono uppercase" x-model="form.code" required />
                </div>
                <div>
                    <x-input-label value="Address" />
                    <textarea name="address" rows="2" x-model="form.address"
                              class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"></textarea>
                </div>
                <div>
                    <x-input-label value="Location manager" />
                    <select name="manager_employee_id" x-model="form.manager_employee_id"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                        <option value="">— None —</option>
                        @foreach($managers as $manager)
                            <option value="{{ $manager->id }}">{{ $manager->name }} ({{ $manager->employee_code }}){{ $manager->user_id ? '' : ' — no login' }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-400">Scans in assets transferred to this location (needs a linked login).</p>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="showModal = false" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 rounded-lg border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">Cancel</button>
                    <x-primary-button>Update</x-primary-button>
                </div>
            </form>
        </template>
    </div>
</div>
