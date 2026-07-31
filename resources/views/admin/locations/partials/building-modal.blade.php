<div x-data="{
        showModal: false,
        editId: null,
        locationId: null,
        locationName: '',
        form: { name: '', code: '' },
        open(data) {
            this.editId      = data.edit_id ?? null;
            this.locationId  = data.location_id;
            this.locationName= data.location_name;
            this.form        = this.editId ? { name: data.name, code: data.code } : { name: '', code: '' };
            this.showModal   = true;
        }
     }"
     @open-building-modal.window="open($event.detail)"
     x-show="showModal"
     x-transition.opacity
     class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4"
     style="display:none">
    <div @click.outside="showModal = false"
         class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1" x-text="editId ? 'Edit Building' : 'New Building'"></h2>
        <p class="text-sm text-gray-400 mb-4">Location: <span class="font-medium" x-text="locationName"></span></p>

        <template x-if="!editId">
            <form method="POST" :action="`{{ url('admin/locations') }}/` + locationId + '/buildings'" class="space-y-4">
                @csrf
                <div>
                    <x-input-label value="Building Name *" />
                    <x-text-input name="name" class="mt-1 block w-full" x-model="form.name" required autofocus />
                </div>
                <div>
                    <x-input-label value="Code *" />
                    <x-text-input name="code" class="mt-1 block w-full font-mono uppercase" x-model="form.code" required />
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="showModal = false" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 rounded-lg border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">Cancel</button>
                    <x-primary-button>Create</x-primary-button>
                </div>
            </form>
        </template>

        <template x-if="editId">
            <form method="POST" :action="`{{ url('admin/buildings') }}/` + editId" class="space-y-4">
                @csrf @method('PUT')
                <div>
                    <x-input-label value="Building Name *" />
                    <x-text-input name="name" class="mt-1 block w-full" x-model="form.name" required autofocus />
                </div>
                <div>
                    <x-input-label value="Code *" />
                    <x-text-input name="code" class="mt-1 block w-full font-mono uppercase" x-model="form.code" required />
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="showModal = false" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 rounded-lg border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">Cancel</button>
                    <x-primary-button>Update</x-primary-button>
                </div>
            </form>
        </template>
    </div>
</div>
