<x-app-layout>
    @section('page-title', $employee->exists ? 'Edit Employee' : 'New Employee')

    <div class="max-w-2xl">
        <div class="flex items-center gap-3 mb-6">
            <a href="{{ route('admin.employees.index') }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">
                {{ $employee->exists ? 'Edit Employee' : 'New Employee' }}
            </h1>
        </div>

        <form method="POST"
              action="{{ $employee->exists ? route('admin.employees.update', $employee) : route('admin.employees.store') }}"
              class="space-y-5 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            @csrf
            @if($employee->exists) @method('PUT') @endif

            {{-- Name & Code --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="name" value="Full Name *" />
                    <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $employee->name)" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="employee_code" value="Employee Code *" />
                    <x-text-input id="employee_code" name="employee_code" class="mt-1 block w-full font-mono"
                                  :value="old('employee_code', $employee->employee_code)" placeholder="e.g. EMP-001" required />
                    <x-input-error :messages="$errors->get('employee_code')" class="mt-1" />
                </div>
            </div>

            {{-- Contact --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="email" value="Email" />
                    <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $employee->email)" />
                    <x-input-error :messages="$errors->get('email')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="phone" value="Phone" />
                    <x-text-input id="phone" name="phone" class="mt-1 block w-full" :value="old('phone', $employee->phone)" />
                    <x-input-error :messages="$errors->get('phone')" class="mt-1" />
                </div>
            </div>

            {{-- Organisation --}}
            <div class="pt-2 border-t border-gray-100 dark:border-gray-700">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Organisation</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @php
                        $selects = [
                            'company_id'     => ['Company', $companies],
                            'department_id'  => ['Department', $departments],
                            'branch_id'      => ['Branch', $branches],
                            'designation_id' => ['Designation', $designations],
                        ];
                    @endphp
                    @foreach($selects as $field => [$label, $options])
                        <div>
                            <x-input-label :for="$field" :value="$label" />
                            <select id="{{ $field }}" name="{{ $field }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                <option value="">— None —</option>
                                @foreach($options as $option)
                                    <option value="{{ $option->id }}" @selected((int) old($field, $employee->$field) === $option->id)>{{ $option->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get($field)" class="mt-1" />
                        </div>
                    @endforeach
                    <div>
                        <x-input-label for="date_of_joining" value="Date of Joining" />
                        <x-text-input id="date_of_joining" name="date_of_joining" type="date" class="mt-1 block w-full"
                                      :value="old('date_of_joining', optional($employee->date_of_joining)->format('Y-m-d'))" />
                        <x-input-error :messages="$errors->get('date_of_joining')" class="mt-1" />
                    </div>
                </div>
            </div>

            {{-- Login link --}}
            <div class="pt-2 border-t border-gray-100 dark:border-gray-700">
                <x-input-label for="user_id" value="Linked User Account" />
                <select id="user_id" name="user_id"
                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                    <option value="">— Not a system user —</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" @selected((int) old('user_id', $employee->user_id) === $user->id)>{{ $user->name }} ({{ $user->email }})</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-400">Link this employee to a login account so they receive movement &amp; expiry notifications. Leave blank for people who don't log in.</p>
                <x-input-error :messages="$errors->get('user_id')" class="mt-1" />
            </div>

            {{-- Active --}}
            <div>
                <label for="is_active" class="inline-flex items-center gap-2">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" id="is_active" name="is_active" value="1"
                           @checked(old('is_active', $employee->exists ? $employee->is_active : true))
                           class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
                    <span class="text-sm text-gray-700 dark:text-gray-300">Active</span>
                </label>
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                <a href="{{ route('admin.employees.index') }}"
                   class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-lg border border-gray-300 dark:border-gray-700 transition-colors">
                    Cancel
                </a>
                <x-primary-button>
                    {{ $employee->exists ? 'Update Employee' : 'Create Employee' }}
                </x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
