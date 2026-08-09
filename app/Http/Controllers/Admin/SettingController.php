<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(): View
    {
        $this->authorize('settings.manage');

        return view('admin.settings.tags', [
            'codeType' => Setting::get('tag_code_type', 'qr'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('settings.manage');

        $data = $request->validate([
            'tag_code_type' => 'required|in:qr,barcode',
        ]);

        Setting::set('tag_code_type', $data['tag_code_type']);

        return redirect()->route('admin.settings.tags.edit')->with('success', 'Tag code type updated.');
    }
}
