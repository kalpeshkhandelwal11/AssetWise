<div x-data="{
        showModal: false,
        editId: null,
        floorId: null,
        floorName: '',
        form: { name: '', code: '' },
        open(data) {
            this.editId   = data.edit_id ?? null;
            this.floorId  = data.floor_id;
            this.floorName= data.floor_name;
            this.form     = this.editId ? { name: data.name, code: data.code } : { name: '', code: '' };
            this.showModal = true;
        }
     }"
     @open-room-modal.window="open($event.detail)"
     x-show="showModal"
     x-transition.opacity
     class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4"
     style="display:none">
    <div @click.outside="showModal = false"
         class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1" x-text="editId ? 'Edit Room' : 'New Room'"></h2>
        <p class="text-sm text-gray-400 mb-4">Floor: <span class="font-medium" x-text="floorName"></span></p>

        <template x-if="!editId">
            <form method="POST" :action="`{{ url('admin/floors') }}/` + floorId + '/rooms'" class="space-y-4">
                @csrf
                <div>
                    <x-input-label value="Room Name *" />
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
            <form method="POST" :action="`{{ url('admin/rooms') }}/` + editId" class="space-y-4">
                @csrf @method('PUT')
                <div>
                    <x-input-label value="Room Name *" />
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
