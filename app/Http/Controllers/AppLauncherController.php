<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Models\AppLauncher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AppLauncherController extends AccountBaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->pageTitle         = 'App Launcher Settings';
        $this->activeSettingMenu = 'app_launcher_settings';
    }

    public function index()
    {
        abort_403(user()->permission('manage_app_setting') != 'all');

        $this->launchers = AppLauncher::forCompany(company()?->id)->orderBy('sort_order')->orderBy('id')->get();

        return view('app-launcher-settings.index', $this->data);
    }

    public function create()
    {
        abort_403(user()->permission('manage_app_setting') != 'all');

        $this->nextOrder = (AppLauncher::forCompany(company()?->id)->max('sort_order') ?? 0) + 1;

        $html = view('app-launcher-settings.ajax.create', $this->data)->render();
        return Reply::dataOnly(['status' => 'success', 'html' => $html]);
    }

    public function store(Request $request)
    {
        abort_403(user()->permission('manage_app_setting') != 'all');

        $request->validate([
            'name'       => ['required', 'string', 'max:191', function ($attribute, $value, $fail) {
                if (preg_match('/[\x{1F000}-\x{1FAFF}\x{2300}-\x{23FF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}\x{200D}\x{20E3}]/u', $value)) {
                    $fail('Emojis are not allowed.');
                }
            }],
            'icon'       => 'required|string|max:191',
            'color'      => 'nullable|string|max:50',
            'route'      => 'nullable|string|max:191',
            'url'        => 'nullable|string|max:191',
            'sort_order' => 'required|integer|min:1',
            'target'     => 'nullable|in:_self,_blank',
        ]);

        DB::beginTransaction();
        try {
            // Auto-shift others at/after this position
            AppLauncher::where('sort_order', '>=', $request->sort_order)
                ->orderBy('sort_order', 'desc')
                ->each(function ($row) {
                    $row->sort_order = $row->sort_order + 1;
                    $row->save();
                });

            // Unique slug
            $slug = Str::slug($request->name);
            $base = $slug; $i = 1;
            while (AppLauncher::forCompany(company()?->id)->where('slug', $slug)->exists()) {
                $slug = $base . '-' . $i++;
            }

            AppLauncher::create([
                'company_id' => company()?->id,
                'name'       => $request->name,
                'slug'       => $slug,
                'icon'       => $request->icon,
                'color'      => $request->color ?: '#4f46e5',
                'route'      => $request->route,
                'url'        => $request->url,
                'sort_order' => $request->sort_order,
                'target'     => $request->target ?: '_self',
                'is_active'  => 1,
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return Reply::error('Failed to add launcher: ' . $e->getMessage());
        }

        cache()->forget('app_launchers_all');
        return Reply::success('App Launcher added successfully.');
    }

    public function edit($id)
    {
        abort_403(user()->permission('manage_app_setting') != 'all');

        $this->launcher = AppLauncher::findOrFail($id);

        $html = view('app-launcher-settings.ajax.edit', $this->data)->render();
        return Reply::dataOnly(['status' => 'success', 'html' => $html]);
    }

    public function update(Request $request, $id)
    {
        abort_403(user()->permission('manage_app_setting') != 'all');

        $launcher = AppLauncher::findOrFail($id);
        $oldSort  = $launcher->sort_order;

        $request->validate([
            'name'       => ['required', 'string', 'max:191', function ($attribute, $value, $fail) {
                if (preg_match('/[\x{1F000}-\x{1FAFF}\x{2300}-\x{23FF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}\x{200D}\x{20E3}]/u', $value)) {
                    $fail('Emojis are not allowed.');
                }
            }],
            'icon'       => 'required|string|max:191',
            'color'      => 'nullable|string|max:50',
            'route'      => 'nullable|string|max:191',
            'url'        => 'nullable|string|max:191',
            'sort_order' => 'required|integer|min:1',
            'target'     => 'nullable|in:_self,_blank',
        ]);

        $newSort = (int) $request->sort_order;

        DB::beginTransaction();
        try {
            if ($oldSort != $newSort) {
                // Temp move to avoid collision
                $launcher->sort_order = 99999;
                $launcher->save();

                // Close old gap
                AppLauncher::where('sort_order', '>', $oldSort)
                    ->where('id', '!=', $id)
                    ->orderBy('sort_order', 'asc')
                    ->each(function ($row) {
                        $row->sort_order = $row->sort_order - 1;
                        $row->save();
                    });

                // Open new space
                AppLauncher::where('sort_order', '>=', $newSort)
                    ->where('id', '!=', $id)
                    ->orderBy('sort_order', 'desc')
                    ->each(function ($row) {
                        $row->sort_order = $row->sort_order + 1;
                        $row->save();
                    });
            }

            $launcher->update([
                'name'       => $request->name,
                'icon'       => $request->icon,
                'color'      => $request->color ?: '#4f46e5',
                'route'      => $request->route,
                'url'        => $request->url,
                'sort_order' => $newSort,
                'target'     => $request->target ?: '_self',
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return Reply::error('Failed to update: ' . $e->getMessage());
        }

        cache()->forget('app_launchers_all');
        return Reply::success('App Launcher updated successfully.');
    }

    public function destroy($id)
    {
        abort_403(user()->permission('manage_app_setting') != 'all');

        AppLauncher::findOrFail($id)->delete();
        cache()->forget('app_launchers_all');

        return Reply::success('App Launcher deleted successfully.');
    }

    public function toggleActive($id)
    {
        abort_403(user()->permission('manage_app_setting') != 'all');

        $launcher = AppLauncher::findOrFail($id);
        $launcher->is_active = !$launcher->is_active;
        $launcher->save();

        cache()->forget('app_launchers_all');
        return Reply::success('Status updated.');
    }
}
