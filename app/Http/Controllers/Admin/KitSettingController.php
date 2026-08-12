<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KitSettingController extends Controller
{
    public function edit(): View
    {
        $this->authorize('kits.manage');

        return view('admin.settings.kits', [
            'mode' => Setting::get('kit_assignment_approval_mode', 'single'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('kits.manage');

        $data = $request->validate([
            'kit_assignment_approval_mode' => 'required|in:single,per_asset',
        ]);

        Setting::set('kit_assignment_approval_mode', $data['kit_assignment_approval_mode']);

        return redirect()->route('admin.settings.kits.edit')->with('success', 'Kit approval mode updated.');
    }
}
