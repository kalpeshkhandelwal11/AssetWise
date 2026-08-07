<x-app-layout>
    @section('page-title', 'Approvals')

    <div class="mb-6">
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Approvals</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Requests awaiting your decision</p>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">My Inbox</h2>
            <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">{{ $requests->count() }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Subject</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Workflow</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Current Step</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Submitted By</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Age</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($requests as $request)
                        @php $step = $request->currentStepDefinition(); @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors align-top">
                            <td class="px-4 py-3">
                                <a href="{{ route('approvals.show', $request) }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                                    {{ $request->approvable_label }}
                                </a>
                                <p class="text-xs text-gray-400">{{ Str::headline($request->workflow->module) }}</p>
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $request->workflow->name }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                Level {{ $request->current_step }}
                                @if($step)
                                    <span class="block text-xs text-gray-400">{{ $step->approver_label }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $request->submittedBy?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $request->current_step_started_at->diffForHumans() }}</td>
                            <td class="px-4 py-3">
                                <div x-data="{ rejecting: false }" class="flex flex-col items-end gap-2">
                                    <div class="flex items-center gap-2">
                                        <form method="POST" action="{{ route('approvals.approve', $request) }}">
                                            @csrf
                                            <button type="submit"
                                                    class="px-3 py-1.5 text-xs font-medium bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                                                Approve
                                            </button>
                                        </form>
                                        <button type="button" @click="rejecting = !rejecting"
                                                class="px-3 py-1.5 text-xs font-medium text-red-600 dark:text-red-400 border border-red-300 dark:border-red-800 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                                            Reject
                                        </button>
                                    </div>

                                    <form x-show="rejecting" x-cloak method="POST" action="{{ route('approvals.reject', $request) }}" class="w-64 space-y-2">
                                        @csrf
                                        <textarea name="comment" rows="2" required placeholder="Reason for rejection (required)"
                                                  class="block w-full text-xs rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                                        <button type="submit"
                                                class="w-full px-3 py-1.5 text-xs font-medium bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                                            Confirm Rejection
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-sm text-gray-400">Nothing awaiting your approval.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Requests this user submitted, so a submitter can follow their own items. --}}
    <div class="mt-8 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">My Submissions</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Subject</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Workflow</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Submitted</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($submitted as $request)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                            <td class="px-4 py-3">
                                <a href="{{ route('approvals.show', $request) }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                                    {{ $request->approvable_label }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $request->workflow->name }}</td>
                            <td class="px-4 py-3">
                                <x-approval-status :status="$request->status" />
                            </td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $request->created_at->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-10 text-center text-sm text-gray-400">You have not submitted any requests.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
