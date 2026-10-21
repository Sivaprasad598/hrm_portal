<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Models\AssetType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Assets -> Asset Types: the categories assets are filed under. */
class AssetTypeController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'Asset Types';

        $this->middleware(function ($request, $next) {
            abort_403(user()->permission('manage_assets') !== 'all');

            return $next($request);
        });
    }

    public function index()
    {
        $this->types = AssetType::withCount('assets')->orderBy('name')->get();

        return view('asset-types.index', $this->data);
    }

    public function store(Request $request)
    {
        $request->validate(['name' => $this->nameRule()]);

        AssetType::create(['company_id' => company()->id, 'name' => trim($request->name)]);

        return Reply::success('Asset type added.');
    }

    public function update(Request $request, $id)
    {
        $type = AssetType::findOrFail($id);
        $request->validate(['name' => $this->nameRule($type->id)]);
        $type->update(['name' => trim($request->name)]);

        return Reply::success('Asset type renamed.');
    }

    public function destroy($id)
    {
        $type = AssetType::withCount('assets')->findOrFail($id);

        if ($type->assets_count) {
            return Reply::error($type->assets_count . ' asset(s) are filed under ' . $type->name . '. Move them to another type first.');
        }

        $type->delete();

        return Reply::success('Asset type deleted.');
    }

    private function nameRule(?int $ignore = null): array
    {
        return ['required', 'string', 'max:100',
            Rule::unique('asset_types', 'name')->where('company_id', company()->id)->ignore($ignore)];
    }

}
