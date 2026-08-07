{{-- Add/edit an approval step. A step is only four fields, so a modal is proportionate. --}}
<div x-data="{
        showModal: false,
        editId: null,
        form: { level: 1, approver_type: 'role', approver_role: '', approver_user_id: '', escalation_hours: '' },
        open(data) {
            this.editId = data.edit_id ?? null;
            this.form = {
                level:            data.level ?? data.next_level ?? 1,
                approver_type:    data.approver_type ?? 'role',
                approver_role:    data.approver_role ?? '',
                approver_user_id: data.approver_user_id ?? '',
                escalation_hours: data.escalation_hours ?? '',
            };
            this.showModal = true;
        }
     }"
     @open-step-modal.window="open($event.detail)"
     x-show="showModal"
     x-transition.opacity
     class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4"
     style="display:none">
    <div @click.outside="showModal = false"
         class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4" x-text="editId ? 'Edit Step' : 'New Step'"></h2>

        <form method="POST"
              :action="editId
                ? `{{ url('admin/workflows/' . $workflow->id . '/steps') }}/` + editId
                : `{{ route('admin.workflows.steps.store', $workflow) }}`"
              class="space-y-4">
            @csrf
            <template x-if="editId"><input type="hidden" name="_method" value="PUT"></template>

            <div>
                <x-input-label value="Level *" />
                <x-text-input name="level" type="number" min="1" class="mt-1 block w-full" x-model="form.level" required />
                <p class="mt-1 text-xs text-gray-400">Steps are approved in ascending level order.</p>
                <x-input-error :messages="$errors->get('level')" class="mt-1" />
            </div>

            <div>
                <x-input-label value="Approver Type *" />
                <select name="approver_type" x-model="form.approver_type"
                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                    <option value="role">Role</option>
                    <option value="user">Specific User</option>
                </select>
            </div>

            <div x-show="form.approver_type === 'role'">
                <x-input-label value="Role *" />
                <select name="approver_role" x-model="form.approver_role" :disabled="form.approver_type !== 'role'"
                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                    <option value="">— Select a role —</option>
                    @foreach($roles as $role)
                        <option value="{{ $role }}">{{ $role }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('approver_role')" class="mt-1" />
            </div>

            <div x-show="form.approver_type === 'user'">
                <x-input-label value="User *" />
                <select name="approver_user_id" x-model="form.approver_user_id" :disabled="form.approver_type !== 'user'"
                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                    <option value="">— Select a user —</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('approver_user_id')" class="mt-1" />
            </div>

            <div>
                <x-input-label value="Escalation Hours" />
                <x-text-input name="escalation_hours" type="number" min="1" class="mt-1 block w-full" x-model="form.escalation_hours" />
                <p class="mt-1 text-xs text-gray-400">Leave blank to never escalate. On escalation the next level's approver may also act.</p>
                <x-input-error :messages="$errors->get('escalation_hours')" class="mt-1" />
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" @click="showModal = false"
                        class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 rounded-lg border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">Cancel</button>
                <x-primary-button x-text="editId ? 'Update Step' : 'Add Step'"></x-primary-button>
            </div>
        </form>
    </div>
</div>
