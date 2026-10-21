<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Models\BaseModel;
use App\Models\LeadProject;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Project names for lead contacts: the "Add" pop-up beside Project Name on the lead form.
 */
class LeadProjectController extends AccountBaseController
{

    public function create()
    {
        abort_403(!in_array(user()->permission('add_lead'), ['all', 'added', 'both']));

        return view('lead-contact.create-project-modal');
    }

    public function store(Request $request)
    {
        abort_403(!in_array(user()->permission('add_lead'), ['all', 'added', 'both']));

        $request->merge(['name' => preg_replace('/\s+/', ' ', trim((string) $request->name))]);

        $request->validate([
            'name' => ['required', 'string', 'max:191', Rule::unique('lead_projects', 'name')->where('company_id', company()->id)],
        ], [
            'name.required' => 'Enter the project name.',
            'name.unique'   => 'This project name already exists.',
        ]);

        // company_id is set here: the dropdown only lists the company's project names (HasCompany scope)
        $project = LeadProject::create(['company_id' => company()->id, 'name' => $request->name, 'added_by' => user()->id]);

        // The select's options, with the new project selected
        return Reply::successWithData(__('messages.recordSaved'), [
            'data' => BaseModel::options(LeadProject::orderBy('name')->get(), $project),
        ]);
    }

}
