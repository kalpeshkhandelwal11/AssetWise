@php
    $humanize = fn (string $perm) => ucwords(str_replace(['.', '_'], ' ', $perm));
    $isSuperAdmin = $role->exists && $role->name === 'Super Admin';
@endphp
<x-app-layout>
    @section('page-title', $role->exists ? 'Edit Role' : 'New Role')

    <div class="max-w-4xl">
        <div class="flex items-center gap-3 mb-6">
            <a href="{{ route('admin.roles.index') }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">
                {{ $role->exists ? 'Edit Role' : 'New Role' }}
            </h1>
        </div>

        @if($role->exists && $role->isLocked())
            <div class="mb-5 px-4 py-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400 text-sm">
                This is a seeded role — its name cannot be changed and it cannot be deleted.
                @if($isSuperAdmin)
                    Its permission set is immutable and always includes every permission.
                @endif
            </div>
        @endif

        <form method="POST"
              action="{{ $role->exists ? route('admin.roles.update', $role) : route('admin.roles.store') }}"
              class="space-y-5 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            @csrf
            @if($role->exists) @method('PUT') @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="name" value="Role Name *" />
                    <x-text-input id="name" name="name" class="mt-1 block w-full"
                                  :value="old('name', $role->name)" required
                                  :disabled="$role->exists && $role->isLocked()" />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="session_lifetime_minutes" value="Session Lifetime (minutes)" />
                    <x-text-input id="session_lifetime_minutes" name="session_lifetime_minutes" type="number" min="1" class="mt-1 block w-full"
                                  :value="old('session_lifetime_minutes', $role->session_lifetime_minutes)" placeholder="Default (480)" />
                    <x-input-error :messages="$errors->get('session_lifetime_minutes')" class="mt-1" />
                </div>
            </div>

            <div class="pt-2 border-t border-gray-100 dark:border-gray-700">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Permissions</p>
                <x-input-error :messages="$errors->get('permissions')" class="mb-3" />

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    @foreach($permissionGroups as $group => $perms)
                        <div x-data="{ selectAll: {{ collect($perms)->every(fn($p) => in_array($p, old('permissions', $rolePermissions))) ? 'true' : 'false' }} }"
                             class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $group }}</p>
                                <label class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                                    <input type="checkbox" x-model="selectAll"
                                           @change="$refs.group_{{ Str::slug($group) }}.querySelectorAll('input[type=checkbox]').forEach(cb => cb.checked = selectAll)"
                                           @if($isSuperAdmin) disabled @endif
                                           class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 focus:ring-indigo-500">
                                    Select all
                                </label>
                            </div>
                            <div x-ref="group_{{ Str::slug($group) }}" class="space-y-1.5">
                                @foreach($perms as $perm)
                                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                        <input type="checkbox" name="permissions[]" value="{{ $perm }}"
                                               @checked($isSuperAdmin || in_array($perm, old('permissions', $rolePermissions)))
                                               @if($isSuperAdmin) disabled @endif
                                               class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 focus:ring-indigo-500">
                                        <span>{{ $humanize($perm) }}</span>
                                        <span class="text-xs text-gray-400 font-mono">{{ $perm }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                @if($isSuperAdmin)
                    {{-- Disabled checkboxes are not submitted — resend the full set so the
                         locked permission array survives a validation-error round trip. --}}
                    @foreach($permissionGroups as $perms)
                        @foreach($perms as $perm)
                            <input type="hidden" name="permissions[]" value="{{ $perm }}">
                        @endforeach
                    @endforeach
                @endif
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                <a href="{{ route('admin.roles.index') }}"
                   class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-lg border border-gray-300 dark:border-gray-700 transition-colors">
                    Cancel
                </a>
                <x-primary-button>
                    {{ $role->exists ? 'Update Role' : 'Create Role' }}
                </x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
