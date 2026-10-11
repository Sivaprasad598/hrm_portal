<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Http\Requests\Admin\ProjectLabel\StoreRequest;
use App\Models\Project;
use App\Models\ProjectLabel;
use App\Models\ProjectLabelList;
use Illuminate\Http\Request;

class ProjectLabelController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'app.menu.projectLabel';
    }

    public function create()
    {
        $this->projectLabels = ProjectLabelList::all();
        $this->projects = Project::all();
        $this->projectId = request()->project_id ?? null;
        $this->projectTemplateProjectId = request()->project_template_project_id;
        return view('projects.create_label', $this->data);
    }

    public function store(StoreRequest $request)
    {
        abort_403(user()->permission('project_labels') !== 'all');
        $projectLabel = new ProjectLabelList();
        $this->storeLabel($request, $projectLabel);

        $allProjectLabels = ProjectLabelList::all();

        if($request->project_id){
            $project = Project::with('label')->findOrFail($request->project_id);
            $currentTaskLable = $project->label;
        }
        else {
            $currentTaskLable = collect([]);
        }


        $labels = '';

        foreach ($allProjectLabels as $key => $value) {

            $selected = '';

            foreach ($currentTaskLable as $item){
                if (is_object($item) && $item->label_id == $value->id) {
                    $selected = 'selected';
                } elseif (is_string($item) && $item == $value->id) {
                    $selected = 'selected';
                }
            }

            $labels .= '<option value="' . $value->id . '" data-content="<span class=\'badge badge-secondary\' style=\'background-color: ' . $value->label_color . '\'>' . $value->label_name . '</span>" '.$selected.'>' . $value->label_name . '</option>';
        }

        return Reply::successWithData(__('messages.recordSaved'), ['data' => $labels]);
    }

    public function update(Request $request, $id)
    {
        abort_403(user()->permission('project_labels') !== 'all');

        $projectLabel = ProjectLabelList::findOrFail($id);

        $this->storeUpdate($request, $projectLabel);

        $allProjectLabels = ProjectLabelList::all();
        $labels = '';

        foreach ($allProjectLabels as $key => $value) {
            $labels .= '<option value="' . $value->id . '" data-content="<span class=\'badge badge-secondary\' style=\'background-color: ' . $value->label_color . '\'>' . $value->label_name . '</span>">' . $value->label_name . '</option>';
        }

        return Reply::successWithData(__('messages.recordSaved'), ['data' => $labels]);
    }

    private function storeLabel($request, $projectLabel)
    {
        $projectLabel->company_id = company()->id;
        $projectLabel->label_name = trim($request->label_name);
        $projectLabel->description = trim_editor($request->description);
        $projectLabel->added_by = user()->id;

        if ($request->has('color')) {
            $projectLabel->color = $request->color;
        }

        $projectLabel->save();

        return $projectLabel;
    }

    private function storeUpdate($request, $projectLabel)
    {

        if($request->label_name != null){
            $projectLabel->label_name = trim($request->label_name);
        }

        if($request->description != null){
            $projectLabel->description = trim_editor($request->description);
        }

        if ($request->has('color')) {
            $projectLabel->color = $request->color;
        }

        $projectLabel->save();

        return $projectLabel;
    }

    public function destroy($id)
    {
        abort_403(user()->permission('project_labels') !== 'all');

        ProjectLabelList::destroy($id);

        $allProjectLabels = ProjectLabelList::all();

        $labels = '';

        foreach ($allProjectLabels as $key => $value) {

            $selected = '';
            $labels .= '<option value="' . $value->id . '" data-content="<span class=\'badge badge-secondary\' style=\'background-color: ' . $value->label_color . '\'>' . $value->label_name . '</span>" '.$selected.'>' . $value->label_name . '</option>';
        }

        return Reply::successWithData(__('messages.recordSaved'), ['data' => $labels]);
    }

    public function labels($id)
    {
        $options = '';
        $labels = ProjectLabelList::all();

        foreach ($labels as $item) {
            $options .= '<option value="' . $item->id . '" data-content="<span class=\'badge badge-secondary\' style=\'background-color: ' . $item->label_color . '\'>' . $item->label_name . '</span>" >' . $item->label_name . '</option>';
        }

        return Reply::dataOnly(['status' => 'success', 'data' => $options]);
    }

}

