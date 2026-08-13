<x-app-layout>
    @section('page-title', $user->exists ? 'Edit User' : 'New User')

    <div class="max-w-2xl">
        <div class="flex items-center gap-3 mb-6">
            <a href="{{ route('admin.users.index') }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">
                {{ $user->exists ? 'Edit User' : 'New User' }}
            </h1>
        </div>

        <form method="POST"
              action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}"
              class="space-y-5 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            @csrf
            @if($user->exists) @method('PUT') @endif

            {{-- Name & Email --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="name" value="Full Name *" />
                    <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="email" value="Email *" />
                    <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required />
                    <x-input-error :messages="$errors->get('email')" class="mt-1" />
                </div>
            </div>

            {{-- Password --}}
            <div>
                <x-input-label for="password" :value="$user->exists ? 'New Password' : 'Password *'" />
                <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" :required="! $user->exists" />
                <p class="mt-1 text-xs text-gray-400">
                    @if($user->exists)
                        Leave blank to keep the current password.
                    @endif
                    Minimum 8 characters, with mixed case, a number, and a symbol.
                </p>
                <x-input-error :messages="$errors->get('password')" class="mt-1" />
            </div>

            {{-- Org fields --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <x-input-label for="department_id" value="Department" />
                    <x-searchable-select id="department_id" name="department_id">
                        <option value="">— None —</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}" @selected(old('department_id', $user->department_id) == $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </x-searchable-select>
                    <x-input-error :messages="$errors->get('department_id')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="branch_id" value="Branch" />
                    <x-searchable-select id="branch_id" name="branch_id">
                        <option value="">— None —</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" @selected(old('branch_id', $user->branch_id) == $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </x-searchable-select>
                    <x-input-error :messages="$errors->get('branch_id')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="designation_id" value="Designation" />
                    <x-searchable-select id="designation_id" name="designation_id">
                        <option value="">— None —</option>
                        @foreach($designations as $designation)
                            <option value="{{ $designation->id }}" @selected(old('designation_id', $user->designation_id) == $designation->id)>{{ $designation->name }}</option>
                        @endforeach
                    </x-searchable-select>
                    <x-input-error :messages="$errors->get('designation_id')" class="mt-1" />
                </div>
            </div>

            {{-- Roles --}}
            <div class="pt-2 border-t border-gray-100 dark:border-gray-700">
                <x-input-label value="Roles *" />
                <div class="mt-2 grid grid-cols-2 sm:grid-cols-3 gap-2">
                    @foreach($roles as $role)
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input type="checkbox" name="roles[]" value="{{ $role->name }}"
                                   class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 focus:ring-indigo-500"
                                   @checked(in_array($role->name, old('roles', $userRoles)))>
                            {{ $role->name }}
                        </label>
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('roles')" class="mt-1" />
            </div>

            {{-- Flags --}}
            <div class="flex items-center gap-6 pt-2 border-t border-gray-100 dark:border-gray-700">
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" name="is_active" value="1"
                           class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 focus:ring-indigo-500"
                           @checked(old('is_active', $user->is_active))>
                    Active
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" name="must_change_password" value="1"
                           class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 focus:ring-indigo-500"
                           @checked(old('must_change_password', $user->must_change_password))>
                    Force password change at next login
                </label>
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                <a href="{{ route('admin.users.index') }}"
                   class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-lg border border-gray-300 dark:border-gray-700 transition-colors">
                    Cancel
                </a>
                <x-primary-button>
                    {{ $user->exists ? 'Update User' : 'Create User' }}
                </x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
