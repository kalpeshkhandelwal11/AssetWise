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

            {{-- Legal name --}}
            <div>
                <x-input-label for="legal_name" value="Legal / Registered Name" />
                <x-text-input id="legal_name" name="legal_name" class="mt-1 block w-full"
                              :value="old('legal_name', $company->legal_name)" placeholder="Full statutory name" />
                <x-input-error :messages="$errors->get('legal_name')" class="mt-1" />
            </div>

            {{-- Hierarchy --}}
            <div class="pt-2 border-t border-gray-100 dark:border-gray-700">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Hierarchy</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
                    <div>
                        <x-input-label for="parent_company_id" value="Parent Company" />
                        <select id="parent_company_id" name="parent_company_id"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            <option value="">— None (top level) —</option>
                            @foreach($companies as $c)
                                <option value="{{ $c->id }}" @selected((int) old('parent_company_id', $company->parent_company_id) === $c->id)>
                                    {{ $c->name }} ({{ $c->code }})
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('parent_company_id')" class="mt-1" />
                    </div>
                    <div>
                        <label for="is_head_office" class="inline-flex items-center gap-2 mt-7">
                            <input type="hidden" name="is_head_office" value="0">
                            <input type="checkbox" id="is_head_office" name="is_head_office" value="1"
                                   @checked(old('is_head_office', $company->is_head_office))
                                   class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm text-gray-700 dark:text-gray-300">This is a Head Office (HO)</span>
                        </label>
                        <x-input-error :messages="$errors->get('is_head_office')" class="mt-1" />
                    </div>
                </div>
            </div>

            {{-- Legal & Tax --}}
            <div class="pt-2 border-t border-gray-100 dark:border-gray-700">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Legal &amp; Tax Identifiers</p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="gstin" value="GSTIN" />
                        <x-text-input id="gstin" name="gstin" class="mt-1 block w-full font-mono uppercase"
                                      :value="old('gstin', $company->gstin)" placeholder="15 characters" maxlength="15" />
                        <x-input-error :messages="$errors->get('gstin')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="pan" value="PAN" />
                        <x-text-input id="pan" name="pan" class="mt-1 block w-full font-mono uppercase"
                                      :value="old('pan', $company->pan)" placeholder="10 characters" maxlength="10" />
                        <x-input-error :messages="$errors->get('pan')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="cin" value="CIN" />
                        <x-text-input id="cin" name="cin" class="mt-1 block w-full font-mono uppercase"
                                      :value="old('cin', $company->cin)" placeholder="21 characters" maxlength="21" />
                        <x-input-error :messages="$errors->get('cin')" class="mt-1" />
                    </div>
                </div>
            </div>

            {{-- Registered address --}}
            <div class="pt-2 border-t border-gray-100 dark:border-gray-700">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Registered Address</p>
                <div>
                    <x-input-label for="registered_address" value="Address" />
                    <textarea id="registered_address" name="registered_address" rows="2"
                              class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">{{ old('registered_address', $company->registered_address) }}</textarea>
                    <x-input-error :messages="$errors->get('registered_address')" class="mt-1" />
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mt-4">
                    <div>
                        <x-input-label for="registered_city" value="City" />
                        <x-text-input id="registered_city" name="registered_city" class="mt-1 block w-full"
                                      :value="old('registered_city', $company->registered_city)" />
                        <x-input-error :messages="$errors->get('registered_city')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="registered_state" value="State" />
                        <x-text-input id="registered_state" name="registered_state" class="mt-1 block w-full"
                                      :value="old('registered_state', $company->registered_state)" />
                        <x-input-error :messages="$errors->get('registered_state')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="registered_pincode" value="Pincode" />
                        <x-text-input id="registered_pincode" name="registered_pincode" class="mt-1 block w-full"
                                      :value="old('registered_pincode', $company->registered_pincode)" />
                        <x-input-error :messages="$errors->get('registered_pincode')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="registered_country" value="Country" />
                        <x-text-input id="registered_country" name="registered_country" class="mt-1 block w-full"
                                      :value="old('registered_country', $company->registered_country)" />
                        <x-input-error :messages="$errors->get('registered_country')" class="mt-1" />
                    </div>
                </div>
            </div>

            {{-- Address --}}
            <div>
                <x-input-label for="address" value="Operating Address" />
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
