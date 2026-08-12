<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssetNamingSettingController extends Controller
{
    public function edit(): View
    {
        $this->authorize('settings.manage');

        return view('admin.settings.asset-naming', [
            'enabled' => Setting::get('asset_naming_enabled', '1') === '1',
            'prefix'  => Setting::get('asset_naming_prefix', 'AST'),
            'padding' => (int) Setting::get('asset_naming_padding', '4'),
            'next'    => (int) Setting::get('asset_naming_next', '1'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('settings.manage');

        $data = $request->validate([
            'asset_naming_enabled' => 'sometimes|boolean',
            'asset_naming_prefix'  => 'required|string|max:20|alpha_dash',
            'asset_naming_padding' => 'required|integer|min:1|max:10',
            'asset_naming_next'    => 'required|integer|min:1',
        ]);

        Setting::set('asset_naming_enabled', $request->boolean('asset_naming_enabled') ? '1' : '0');
        Setting::set('asset_naming_prefix', strtoupper($data['asset_naming_prefix']));
        Setting::set('asset_naming_padding', (string) $data['asset_naming_padding']);
        Setting::set('asset_naming_next', (string) $data['asset_naming_next']);

        return redirect()->route('admin.settings.asset-naming.edit')
            ->with('success', 'Asset naming series updated.');
    }
}
