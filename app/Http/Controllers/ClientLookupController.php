<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Models\Project;
use App\Models\User;
use App\Scopes\ActiveScope;
use App\Models\BaseModel;
use Illuminate\Http\Request;

/**
 * Client lookups used by core forms (projects, contracts, invoices, estimates, orders).
 * Kept in core so those forms work in every branch; the Clients pages themselves
 * are the Clients module (branch hrm_v1_module_clients). Same URLs and route names.
 */
class ClientLookupController extends AccountBaseController
{

    /**
     * XXXXXXXXXXX
     *
     * @return \Illuminate\Http\Response
     */
    public function projectList($id)
    {
        if ($id != 0) {
            $projects = Project::where('client_id', $id)->get();
            $options = BaseModel::options($projects, null, 'project_name');

        }
        else {
            $options = '<option value="">--</option>';
        }

        return Reply::dataOnly(['status' => 'success', 'data' => $options]);
    }

    /**
     * XXXXXXXXXXX
     *
     * @return \Illuminate\Http\Response
     */
    public function ajaxDetails($id)
    {
        if ($id != 0) {
            $client = User::withoutGlobalScope(ActiveScope::class)->with('clientDetails', 'country')->find($id);

        }
        else {
            $client = null;
        }

        $clientProjects = Project::where('client_id', $id)->get();

        $options = '<option value="">--</option>';

        foreach ($clientProjects as $project) {

            $options .= '<option value="' . $project->id . '"> ' . $project->project_name . ' </option>';
        }

        $data = $client ?: null;

        return Reply::dataOnly(['status' => 'success', 'data' => $data, 'project' => $options]);
    }

    public function clientDetails(Request $request)
    {
        $teamData = '';

        if ($request->id == 0) {
            $clients = User::allClients();

            foreach ($clients as $client) {

                $teamData .= '<option data-content="';

                $teamData .= '<div class=\'media align-items-center mw-250\'>';

                $teamData .= '<div class=\'position-relative\'><img src=' . $client->image_url . ' class=\'mr-2 taskEmployeeImg rounded-circle\'></div>';
                $teamData .= '<div class=\'media-body\'>';
                $teamData .= '<h5 class=\'mb-0 f-13\'>' . $client->name . '</h5>';
                $teamData .= '<p class=\'my-0 f-11 text-dark-grey\'>' . $client->email . '</p>';

                $teamData .= (!is_null($client->clientDetails->company_name)) ? '<p class=\'my-0 f-11 text-dark-grey\'>' . $client->clientDetails->company_name . '</p>' : '';
                $teamData .= '</div>';
                $teamData .= '</div>"';

                $teamData .= 'value="' . $client->id . '"> ' . $client->name . '';

                $teamData .= '</option>';

            }
        }
        else {

            $project = Project::with('client')->findOrFail($request->id);

            if ($project->client != null) {

                $teamData .= '<option data-content="';

                $teamData .= '<div class=\'media align-items-center mw-250\'>';

                $teamData .= '<div class=\'position-relative\'><img src=' . $project->client->image_url . ' class=\'mr-2 taskEmployeeImg rounded-circle\'></div>';
                $teamData .= '<div class=\'media-body\'>';
                $teamData .= '<h5 class=\'mb-0 f-13\'>' . $project->client->name . '</h5>';
                $teamData .= '<p class=\'my-0 f-11 text-dark-grey\'>' . $project->client->email . '</p>';

                $teamData .= (!is_null($project->client->company->company_name)) ? '<p class=\'my-0 f-11 text-dark-grey\'>' . $project->client->company->company_name . '</p>' : '';
                $teamData .= '</div>';
                $teamData .= '</div>"';

                $teamData .= 'value="' . $project->client->id . '"> ' . $project->client->name . '';

                $teamData .= '</option>';
            }

        }

        return Reply::dataOnly(['teamData' => $teamData]);
    }

}
