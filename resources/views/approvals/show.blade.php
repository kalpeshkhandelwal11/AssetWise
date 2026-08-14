<x-app-layout>
    @section('page-title', 'Approval Request')

    <div class="max-w-3xl">
        <div class="flex items-center gap-3 mb-6">
            <a href="{{ route('approvals.index') }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $request->approvable_label }}</h1>
            <x-approval-status :status="$request->status" />
            @if($escalated && $request->isPending())
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400">
                    Escalated
                </span>
            @endif
        </div>

        {{-- Summary --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                <div>
                    <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Workflow</dt>
                    <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $request->workflow->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Module</dt>
                    <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ Str::headline($request->workflow->module) }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Submitted By</dt>
                    <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $request->submittedBy?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Submitted</dt>
                    <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $request->created_at->format('d M Y, H:i') }}</dd>
                </div>
            </dl>
        </div>

        {{-- Bulk movement line items (read-only) — one row per asset in the batch. --}}
        @if($request->approvable instanceof \App\Models\AssetMovementBatch)
            <div class="mt-6 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Assets in this request</h2>
                    <span class="text-xs text-gray-400">{{ $request->approvable->movements->count() }} item(s)</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Asset</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tag</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Destination</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($request->approvable->movements as $line)
                                <tr>
                                    <td class="px-4 py-2 text-gray-900 dark:text-gray-100">
                                        <a href="{{ route('assets.show', $line->asset_id) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ $line->asset?->name ?? 'Asset #'.$line->asset_id }}</a>
                                    </td>
                                    <td class="px-4 py-2 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $line->asset?->asset_tag ?? '—' }}</td>
                                    <td class="px-4 py-2 text-gray-600 dark:text-gray-400 text-xs">
                                        {{ collect([$line->toCompany?->name, $line->toLocation?->name, $line->toDepartment?->name, $line->toCustodian?->name])->filter()->join(' · ') ?: '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="px-6 py-3 text-xs text-gray-400 border-t border-gray-100 dark:border-gray-700">Approve or reject applies to the whole batch.</p>
            </div>
        @endif

        {{-- Supporting documents attached at submission --}}
        @if($request->attachments->isNotEmpty())
            <div class="mt-6 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Supporting Documents</h2>
                </div>
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($request->attachments as $doc)
                        <li class="px-6 py-3 flex items-center gap-3 text-sm">
                            <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                            </svg>
                            <a href="{{ $doc->url }}" target="_blank" rel="noopener" class="text-indigo-600 dark:text-indigo-400 hover:underline truncate">{{ $doc->original_name }}</a>
                            <span class="text-xs text-gray-400 whitespace-nowrap">{{ number_format($doc->size / 1024, 0) }} KB</span>
                            <span class="ml-auto text-xs text-gray-400 whitespace-nowrap">{{ $doc->uploadedBy?->name }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Step chain --}}
        <div class="mt-6 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Approval Chain</h2>
            </div>
            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($request->workflow->steps as $step)
                    @php
                        $isCurrent = $request->isPending() && $step->level === $request->current_step;
                        $isDone    = $step->level < $request->current_step || $request->status === 'approved';
                    @endphp
                    <li class="px-6 py-3 flex items-center gap-3 {{ $isCurrent ? 'bg-amber-50 dark:bg-amber-900/10' : '' }}">
                        <span class="w-6 h-6 flex items-center justify-center rounded-full text-xs font-semibold
                                     {{ $isDone ? 'bg-green-500 text-white' : ($isCurrent ? 'bg-amber-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400') }}">
                            {{ $step->level }}
                        </span>
                        <span class="text-sm text-gray-800 dark:text-gray-200">{{ $step->approver_label }}</span>
                        @if($step->escalation_hours)
                            <span class="text-xs text-gray-400">escalates after {{ $step->escalation_hours }}h</span>
                        @endif
                        @if($isCurrent)
                            <span class="ml-auto text-xs font-medium text-amber-600 dark:text-amber-400">Awaiting decision</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- Actions --}}
        @if($canAct)
            <div class="mt-6 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-4">Your Decision — Level {{ $request->current_step }}</h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <form method="POST" action="{{ route('approvals.approve', $request) }}" class="space-y-3">
                        @csrf
                        <x-input-label for="approve_comment" value="Comment (optional)" />
                        <textarea id="approve_comment" name="comment" rows="3"
                                  class="block w-full text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                        <button type="submit" class="w-full px-4 py-2 text-sm font-medium bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                            Approve
                        </button>
                    </form>

                    <form method="POST" action="{{ route('approvals.reject', $request) }}" class="space-y-3">
                        @csrf
                        <x-input-label for="reject_comment" value="Reason (required)" />
                        <textarea id="reject_comment" name="comment" rows="3" required
                                  class="block w-full text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                        <x-input-error :messages="$errors->get('comment')" />
                        <button type="submit" class="w-full px-4 py-2 text-sm font-medium bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                            Reject
                        </button>
                    </form>
                </div>
                <p class="mt-4 text-xs text-gray-400">Rejection is final for this request — a new request must be submitted to try again.</p>
            </div>
        @endif

        {{-- Append-only history --}}
        <div class="mt-6 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">History</h2>
            </div>
            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($request->actions as $action)
                    <li class="px-6 py-3">
                        <div class="flex items-center gap-2 text-sm">
                            <span class="px-2 py-0.5 rounded-md text-xs font-medium
                                {{ ['approve' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                                    'reject'  => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                                    'escalate'=> 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400'][$action->action] }}">
                                {{ ucfirst($action->action) }}
                            </span>
                            <span class="text-gray-700 dark:text-gray-300">Level {{ $action->step_level }}</span>
                            <span class="text-gray-500 dark:text-gray-400">by {{ $action->user?->name ?? 'System' }}</span>
                            <span class="ml-auto text-xs text-gray-400">{{ $action->created_at->format('d M Y, H:i') }}</span>
                        </div>
                        @if($action->comment)
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $action->comment }}</p>
                        @endif
                    </li>
                @empty
                    <li class="px-6 py-10 text-center text-sm text-gray-400">No actions recorded yet.</li>
                @endforelse
            </ul>
        </div>
    </div>
</x-app-layout>
