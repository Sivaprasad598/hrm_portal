<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Lead Settings → Research Projects: the "Research projects" list of Lead Contacts → Search Projects
 * (lead_finder_projects, type project, per company). Administrators only, like the finder.
 */
class LeadResearchProjectController extends AccountBaseController
{
    private const TYPE = 'project';

    public function store(Request $request)
    {
        abort_403(!in_array('admin', user_roles()));
        $name = $this->validName($request);

        DB::table('lead_finder_projects')->insert([
            'company_id' => company()?->id, 'type' => self::TYPE, 'name' => $name, 'added_by' => user()?->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return response()->json(['status' => 'success', 'name' => $name]);
    }

    public function update(Request $request, $id)
    {
        abort_403(!in_array('admin', user_roles()));
        $this->row($id);
        $name = $this->validName($request, $id);

        DB::table('lead_finder_projects')->where('id', $id)->update(['name' => $name, 'updated_at' => now()]);

        return response()->json(['status' => 'success', 'name' => $name]);
    }

    public function destroy($id)
    {
        abort_403(!in_array('admin', user_roles()));
        $this->row($id);

        DB::table('lead_finder_projects')->where('id', $id)->delete();

        return response()->json(['status' => 'success']);
    }

    /** The research projects of this company, A–Z, with who added them (the settings tab). */
    public static function list()
    {
        return DB::table('lead_finder_projects')
            ->leftJoin('users', 'users.id', '=', 'lead_finder_projects.added_by')
            ->where('lead_finder_projects.company_id', company()?->id)
            ->where('lead_finder_projects.type', self::TYPE)
            ->orderBy('lead_finder_projects.name')
            ->get(['lead_finder_projects.id', 'lead_finder_projects.name', 'lead_finder_projects.created_at', 'users.name as added_by_name']);
    }

    private function row($id)
    {
        $row = DB::table('lead_finder_projects')->where('id', $id)
            ->where('company_id', company()?->id)->where('type', self::TYPE)->first();
        abort_if(!$row, 404);

        return $row;
    }

    /** A trimmed name, at least 2 characters and not already in the list (case-insensitive). */
    private function validName(Request $request, $ignoreId = null): string
    {
        $name = trim(preg_replace('/\s+/', ' ', (string) $request->input('name')));
        $request->merge(['name' => $name])->validate([
            'name' => ['required', 'string', 'min:2', 'max:191',
                Rule::unique('lead_finder_projects', 'name')
                    ->where('company_id', company()?->id)->where('type', self::TYPE)->ignore($ignoreId)],
        ], ['name.unique' => 'This research project already exists.']);

        return $name;
    }
}
