<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoginHistory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoginHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('login_history.view');

        $histories = LoginHistory::with('user')
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->user_id, fn ($q, $v) => $q->where('user_id', $v))
            ->when($request->date_from, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->date_to,   fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest('created_at')
            ->paginate(50)
            ->withQueryString();

        return view('admin.login-history.index', compact('histories'));
    }
}
