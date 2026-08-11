<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboard) {}

    public function __invoke(Request $request): View
    {
        $companyId = $request->filled('company_id') ? $request->integer('company_id') : null;

        return view('dashboard', $this->dashboard->summary($request->user(), $companyId) + [
            'companies'        => Company::orderBy('name')->get(),
            'selectedCompany'  => $companyId,
        ]);
    }
}
