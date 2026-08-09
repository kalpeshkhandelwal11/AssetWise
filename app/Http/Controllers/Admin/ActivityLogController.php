<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('activity_log.view');

        $activities = Activity::with(['causer', 'subject'])
            ->when($request->log_name,     fn ($q, $v) => $q->where('log_name', $v))
            ->when($request->event,        fn ($q, $v) => $q->where('event', $v))
            ->when($request->causer_id,    fn ($q, $v) => $q->where('causer_id', $v))
            ->when($request->subject_type, fn ($q, $v) => $q->where('subject_type', $v))
            ->when($request->date_from,    fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->date_to,      fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.activity-log.index', [
            'activities'   => $activities,
            'logNames'     => Activity::query()->distinct()->pluck('log_name')->filter()->values(),
            'subjectTypes' => Activity::query()->distinct()->pluck('subject_type')->filter()->values(),
            'causers'      => User::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
