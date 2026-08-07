<x-app-layout>
    @section('page-title', $workflow->exists ? 'Edit Workflow' : 'New Workflow')

    <div class="max-w-3xl">
        <div class="flex items-center gap-3 mb-6">
            <a href="{{ route('admin.workflows.index') }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">
                {{ $workflow->exists ? 'Edit Workflow' : 'New Workflow' }}
            </h1>
        </div>

        <form method="POST"
              action="{{ $workflow->exists ? route('admin.workflows.update', $workflow) : route('admin.workflows.store') }}"
              class="space-y-5 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            @csrf
            @if($workflow->exists) @method('PUT') @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="name" value="Workflow Name *" />
                    <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $workflow->name)" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="module" value="Module *" />
                    <select id="module" name="module" required
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                        @foreach($modules as $module)
                            <option value="{{ $module }}" @selected(old('module', $workflow->module) === $module)>{{ Str::headline($module) }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('module')" class="mt-1" />
                </div>
            </div>

            <label class="flex items-start gap-2">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $workflow->is_active ?? true))
                       class="mt-0.5 rounded border-gray-300 dark:border-gray-700 text-indigo-600 focus:ring-indigo-500">
                <span>
                    <span class="text-sm text-gray-700 dark:text-gray-300">Active</span>
                    <span class="block text-xs text-gray-400">Activating this workflow deactivates any other active workflow for the same module.</span>
                </span>
            </label>
            <x-input-error :messages="$errors->get('workflow')" class="mt-1" />

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                <a href="{{ route('admin.workflows.index') }}"
                   class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-lg border border-gray-300 dark:border-gray-700 transition-colors">
                    Cancel
                </a>
                <x-primary-button>{{ $workflow->exists ? 'Update Workflow' : 'Create Workflow' }}</x-primary-button>
            </div>
        </form>

        @if($workflow->exists)
            {{-- Approval steps --}}
            <div class="mt-8 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Approval Steps</h2>
                        <p class="text-xs text-gray-400 mt-0.5">Approved in ascending level order. A step with no escalation hours never escalates.</p>
                    </div>
                    <button type="button"
                            @click="$dispatch('open-step-modal', { next_level: {{ ($workflow->steps->max('level') ?? 0) + 1 }} })"
                            class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add Step
                    </button>
                </div>

                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Level</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Approver</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Escalates After</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($workflow->steps as $step)
                            @php
                                $stepPayload = [
                                    'edit_id'          => $step->id,
                                    'level'            => $step->level,
                                    'approver_type'    => $step->approver_type,
                                    'approver_role'    => $step->approver_role ?? '',
                                    'approver_user_id' => $step->approver_user_id ?? '',
                                    'escalation_hours' => $step->escalation_hours ?? '',
                                ];
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                <td class="px-6 py-3 font-mono text-gray-900 dark:text-gray-100">{{ $step->level }}</td>
                                <td class="px-6 py-3 text-gray-700 dark:text-gray-300">{{ $step->approver_label }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-400">
                                    {{ $step->escalation_hours ? $step->escalation_hours . ' hours' : '—' }}
                                </td>
                                <td class="px-6 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button type="button"
                                                @click="$dispatch('open-step-modal', {{ Js::from($stepPayload) }})"
                                                class="p-1.5 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                                title="Edit">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </button>
                                        <form method="POST" action="{{ route('admin.workflows.steps.destroy', [$workflow, $step]) }}">
                                            @csrf @method('DELETE')
                                            <button type="submit"
                                                    class="p-1.5 text-gray-400 hover:text-red-600 dark:hover:text-red-400 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                                    title="Remove">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-10 text-center text-sm text-gray-400">
                                    No steps yet — a workflow with no steps cannot accept submissions.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @include('admin.workflows.partials.step-modal', ['workflow' => $workflow, 'roles' => $roles, 'users' => $users])
        @endif
    </div>
</x-app-layout>
