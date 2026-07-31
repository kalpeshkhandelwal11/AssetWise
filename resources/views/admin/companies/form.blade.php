<x-app-layout>
    @section('page-title', $company->exists ? 'Edit Company' : 'New Company')

    <div class="max-w-2xl">
        <div class="flex items-center gap-3 mb-6">
            <a href="{{ route('admin.companies.index') }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">
                {{ $company->exists ? 'Edit Company' : 'New Company' }}
            </h1>
        </div>

        <form method="POST"
              action="{{ $company->exists ? route('admin.companies.update', $company) : route('admin.companies.store') }}"
              class="space-y-5 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            @csrf
            @if($company->exists) @method('PUT') @endif

            {{-- Name & Code --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="name" value="Company Name *" />
                    <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $company->name)" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="code" value="Code *" />
                    <x-text-input id="code" name="code" class="mt-1 block w-full font-mono uppercase"
                                  :value="old('code', $company->code)" placeholder="e.g. ACME" required />
                    <p class="mt-1 text-xs text-gray-400">Unique short identifier (letters, numbers, dashes). Auto-uppercased.</p>
                    <x-input-error :messages="$errors->get('code')" class="mt-1" />
                </div>
            </div>

            {{-- Address --}}
            <div>
                <x-input-label for="address" value="Address" />
                <textarea id="address" name="address" rows="2"
                          class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">{{ old('address', $company->address) }}</textarea>
                <x-input-error :messages="$errors->get('address')" class="mt-1" />
            </div>

            {{-- City & Country --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="city" value="City" />
                    <x-text-input id="city" name="city" class="mt-1 block w-full" :value="old('city', $company->city)" />
                    <x-input-error :messages="$errors->get('city')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="country" value="Country" />
                    <x-text-input id="country" name="country" class="mt-1 block w-full" :value="old('country', $company->country)" />
                    <x-input-error :messages="$errors->get('country')" class="mt-1" />
                </div>
            </div>

            {{-- Contact --}}
            <div class="pt-2 border-t border-gray-100 dark:border-gray-700">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Contact Information</p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="contact_name" value="Contact Name" />
                        <x-text-input id="contact_name" name="contact_name" class="mt-1 block w-full"
                                      :value="old('contact_name', $company->contact_name)" />
                        <x-input-error :messages="$errors->get('contact_name')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="contact_email" value="Email" />
                        <x-text-input id="contact_email" name="contact_email" type="email" class="mt-1 block w-full"
                                      :value="old('contact_email', $company->contact_email)" />
                        <x-input-error :messages="$errors->get('contact_email')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="contact_phone" value="Phone" />
                        <x-text-input id="contact_phone" name="contact_phone" class="mt-1 block w-full"
                                      :value="old('contact_phone', $company->contact_phone)" />
                        <x-input-error :messages="$errors->get('contact_phone')" class="mt-1" />
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                <a href="{{ route('admin.companies.index') }}"
                   class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-lg border border-gray-300 dark:border-gray-700 transition-colors">
                    Cancel
                </a>
                <x-primary-button>
                    {{ $company->exists ? 'Update Company' : 'Create Company' }}
                </x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
