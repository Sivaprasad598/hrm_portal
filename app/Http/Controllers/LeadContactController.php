<?php

namespace App\Http\Controllers;

use App\DataTables\DealsDataTable;
use App\DataTables\LeadContactDataTable;
use App\DataTables\LeadNotesDataTable;
use App\Enums\Salutation;
use App\Helper\Reply;
use App\Http\Requests\Admin\Employee\ImportProcessRequest;
use App\Http\Requests\Admin\Employee\ImportRequest;
use App\Http\Requests\Lead\StoreRequest;
use App\Http\Requests\Lead\UpdateRequest;
use App\Imports\LeadImport;
use App\Jobs\ImportLeadJob;
use App\Models\Deal;
use App\Models\LeadAgent;
use App\Models\LeadCategory;
use App\Models\Lead;
use App\Models\LeadCustomForm;
use App\Models\LeadPipeline;
use App\Models\LeadProduct;
use App\Models\LeadSource;
use App\Models\PipelineStage;
use App\Models\LeadStatus;
use App\Models\Product;
use App\Models\User;
use App\Traits\ImportExcel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class LeadContactController extends AccountBaseController
{

    use ImportExcel;

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'modules.leadContact.leadContacts';
        $this->middleware(function ($request, $next) {
            abort_403(!in_array('leads', $this->user->modules));

            return $next($request);
        });
    }

    public function index(LeadContactDataTable $dataTable)
    {
        // An employee who is not the Lead Owner of any lead works only on the lead teams they are assigned to:
        // they get My Lead Teams (lead_team_view) instead of the Lead Contact list
        // Also an employee whose role cannot view leads at all (View Lead = None), Lead Owner or not: My Lead Teams
        // lists the leads they own or are on
        $noLeadList = in_array('employee', user_roles() ?? []) && !in_array('admin', user_roles() ?? [])
            && user()->permission('view_lead') === 'none';

        if (self::teamOnlyEmployee() || $noLeadList) {
            return request()->ajax()
                ? response()->json(['status' => 'fail', 'message' => 'Open My Lead Teams.', 'redirect' => route('lead-contact.my-teams')], 403)
                : redirect()->route('lead-contact.my-teams');
        }

        $this->destroySession();
        $this->viewLeadPermission = $viewPermission = user()->permission('view_lead');

        abort_403(!in_array($viewPermission, ['all','added','owned','both']));

        if (!request()->ajax()) {
            $this->categories = LeadCategory::get();
            $this->sources = LeadSource::get();
            $this->employees = User::allEmployees(null, 'active');
        }

        return $dataTable->render('lead-contact.index', $this->data);

    }

    public function show($id)
    {
        $this->leadContact = Lead::findOrFail($id)->withCustomFields();

        $this->viewPermission = user()->permission('view_lead');

        abort_403(!in_array($this->viewPermission, ['all','added','owned','both']));

        $this->pageTitle = $this->leadContact->client_name_salutation;

        $this->categories = LeadCategory::all();

        $this->leadFormFields = LeadCustomForm::with('customField')->where('status', 'active')->where('custom_fields_id', '!=', 'null')->get();

        $this->leadId = $id;

        $getCustomFieldGroupsWithFields = $this->leadContact->getCustomFieldGroupsWithFields();
        if ($getCustomFieldGroupsWithFields) {
            $this->fields = $getCustomFieldGroupsWithFields->fields;
        }

        $this->editLeadPermission = user()->permission('edit_lead');
        $this->deleteLeadPermission = user()->permission('delete_lead');

        $tab = request('tab');

        switch ($tab) {
        case 'deal':
            return $this->deals();
        case 'notes':
            return $this->notes();
        case 'meetings':
            // Meetings with this lead (made from "Add Meetings" / the Meetings tab)
            // Lead meetings only: Lead Team meetings are on the Lead Team tab
            $this->meetings = \App\Models\CustomerMeeting::with(['lead', 'leadProject', 'attendees'])->where('lead_id', $this->leadContact->id)
                ->role(\App\Models\CustomerMeeting::ROLE_LEAD)
                ->orderByDesc('meeting_date')->orderByDesc('start_time')->get();
            $this->view = 'lead-contact.ajax.meetings';
            break;
        case 'team':
            $this->prepareTeamTab();
            break;
        default:
            $this->view = 'lead-contact.ajax.profile';
            break;
        }

        if (request()->ajax()) {
            return $this->returnAjax($this->view);
        }

        $this->activeTab = $tab ?: 'profile';

        return view('lead-contact.show', $this->data);

    }

    /**
     * The Lead Team tab: lead contact, owner and team members (tick people and "Create Meeting" invites them),
     * Team Chat and Lead Team Meetings. Used by the lead page's Lead Team tab and by the Lead Team page (teamView).
     */
    private function prepareTeamTab(): void
    {
        // Lead Team: lead contact, owner and team members; tick people and "Create Meeting" invites them
        $this->teamPeople = LeadTeamController::people($this->leadContact);
        // "Create Meeting" opens the meeting form below the table, already set to this lead
        $this->leads = Lead::orderBy('client_name')->get();
        $this->clients = collect();
        $this->leadProjects = \App\Models\LeadProject::orderBy('name')->get();
        $this->employees = User::allEmployees(null, true);
        $this->meetingPurposes = \App\Models\MeetingOption::ofType('purpose')->get();
        $this->meetingStatuses = \App\Models\MeetingOption::ofType('status')->get();
        $this->meetingValues = [
            'meeting_type' => 'offline',
            'lead_id' => $this->leadContact->id,
            'lead_project_id' => $this->leadContact->lead_project_id,
            'lead_owner' => $this->leadContact->lead_owner,
        ];
        // "Lead Team Meetings": this lead's Lead Team Meeting records (made from the Lead Team)
        $this->teamMeetings = \App\Models\CustomerMeeting::with(['lead', 'leadProject', 'attendees', 'leadTeamMeeting'])
            ->where('lead_id', $this->leadContact->id)->role(\App\Models\CustomerMeeting::ROLE_LEAD_TEAM)
            ->orderByDesc('meeting_date')->orderByDesc('start_time')->get();
        $this->teamView = in_array(request('view'), ['meetings', 'chat'], true) ? request('view') : 'team';
        $this->view = 'lead-contact.ajax.team';
    }

    /**
     * Lead Team page: the lead page with only its Lead Team tab (lead_team_view). /lead_team_view.php?id= opens it.
     */
    public function teamView($id)
    {
        $this->leadContact = Lead::findOrFail($id)->withCustomFields();
        $this->viewPermission = user()->permission('view_lead');
        // Only the lead's owner, its assigned team, or someone who may see every lead
        abort_403(!self::onLeadTeam($this->leadContact) && $this->viewPermission !== 'all' && !in_array('admin', user_roles()));

        $this->pageTitle = $this->leadContact->client_name_salutation;
        $this->leadId = $id;
        $this->prepareTeamTab();
        // Links that return to the Lead Team (e.g. after creating a meeting) stay on this page
        $this->teamPageUrl = route('lead-contact.team-only', $id);
        $this->activeTab = 'team';

        return view('lead-contact.lead_team_view', $this->data);
    }

    /**
     * My Lead Teams (lead_team_view.php without an id): the leads I am assigned to, as Lead Owner or team member,
     * with the project, the lead owner and my role. Only mine; each opens its Lead Team page.
     */
    public function myTeams()
    {
        $me = user()->id;
        $this->pageTitle = 'My Lead Teams';

        $memberOf = DB::table('lead_team_members')->where('user_id', $me)->pluck('lead_id');

        $this->assignedLeads = Lead::with(['leadProject', 'leadOwner'])
            ->where(fn ($q) => $q->whereIn('id', $memberOf)->orWhere('lead_owner', $me))
            ->with('teamMembers:users.id')
            ->orderBy('client_name')
            ->get()
            ->map(fn ($lead) => [
                'id' => $lead->id,
                'lead' => $lead->client_name,
                'company' => $lead->company_name,
                'project' => $lead->leadProject?->name,
                'owner' => $lead->leadOwner?->name,
                'role' => (int) $lead->lead_owner === $me ? 'Lead Owner' : 'Team Member',
                // owner + team, each person once
                'team_size' => $lead->teamMembers->pluck('id')->push($lead->lead_owner)->filter()->unique()->count(),
                'url' => route('lead-contact.team-only', $lead->id),
            ]);

        return view('lead-contact.my_lead_teams', $this->data);
    }

    /**
     * An employee (not an administrator) who is not the Lead Owner of any lead: they see only their lead teams.
     */
    public static function teamOnlyEmployee(): bool
    {
        $roles = user_roles() ?? [];

        return in_array('employee', $roles) && !in_array('admin', $roles)
            && !Lead::where('lead_owner', user()->id)->exists();
    }

    /** I am this lead's owner or on its assigned team. */
    public static function onLeadTeam(Lead $lead): bool
    {
        return (int) $lead->lead_owner === (int) user()->id
            || DB::table('lead_team_members')->where('lead_id', $lead->id)->where('user_id', user()->id)->exists();
    }

    public function notes()
    {
        $dataTable = new LeadNotesDataTable();
        // Notes follow View Lead Note (they used to check View Deals); the Lead Owner always sees their lead's notes
        $viewPermission = user()->permission('view_lead_note');

        abort_403(!in_array($viewPermission, ['all', 'added', 'owned', 'both']) && !$this->leadContact->isOwnedByMe());

        $tab = request('tab');
        $this->activeTab = $tab ?: 'profile';

        $this->view = 'lead-contact.ajax.notes';

        return $dataTable->render('lead-contact.show', $this->data);
    }

    public function deals()
    {
        $viewPermission = user()->permission('view_deals');

        // The Lead Owner always sees their lead's deals
        abort_403(!in_array($viewPermission, ['all', 'added', 'both', 'owned']) && !$this->leadContact->isOwnedByMe());

        $tab = request('tab');
        $this->pipelines = LeadPipeline::all();

        $defaultPipeline = $this->pipelines->filter(function ($value, $key) {
            return $value->default == 1;
        })->first();

        $this->stages = PipelineStage::where('lead_pipeline_id', $defaultPipeline->id)->get();

        $this->activeTab = $tab ?: 'profile';
        $this->view = 'lead-contact.ajax.deal';
        $dataTable = new DealsDataTable();

        return $dataTable->render('lead-contact.show', $this->data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $this->pageTitle = __('modules.leadContact.createTitle');

        $this->addPermission = user()->permission('add_lead');
        abort_403(!in_array($this->addPermission, ['all', 'added']));

        $this->employees = User::allEmployees(null, true);

        $defaultStatus = LeadStatus::where('default', '1')->first();
        $this->columnId = request('column_id') ?: $defaultStatus->id;

        $this->leadAgents = LeadAgent::whereHas('user', function ($q) {
            $q->where('status', 'active');
        })->with('user')->get();

        $this->leadAgentArray = $this->leadAgents->pluck('user_id')->toArray();


        if ((in_array(user()->id, $this->leadAgentArray))) {
            $this->myAgentId = $this->leadAgents->filter(function ($value, $key) {
                return $value->user_id == user()->id;
            })->first()->id;
        }

        $leadContact = new Lead();

        $getCustomField = $leadContact->getCustomFieldGroupsWithFields();

        if ($getCustomField) {
            $this->fields = $getCustomField->fields;
        }

        $this->sources = LeadSource::all();
        $this->leadProjects = \App\Models\LeadProject::orderBy('name')->get();
        $this->categories = LeadCategory::all();
        $this->countries = countries();
        $this->salutations = Salutation::cases();

        // To create deal from lead

        $this->leadPipelines = LeadPipeline::orderBy('default', 'DESC')->get();
        $this->leadStages = PipelineStage::all();
        $this->leadAgentArray = $this->leadAgents->pluck('user_id')->toArray();
        $this->products = Product::all();


        // Assign Team: Department -> Designation -> Employee
        $this->departments = LeadTeamController::departments();

        $this->view = 'lead-contact.ajax.create';

        if (request()->ajax()) {
            return $this->returnAjax($this->view);
        }

        return view('lead-contact.create', $this->data);

    }

    /**
     * @param StoreRequest $request
     * @return array|void
     * @throws \Froiden\RestAPI\Exceptions\RelatedResourceNotFoundException
     */
    public function store(StoreRequest $request)
    {
        $this->addPermission = user()->permission('add_lead');

        abort_403(!in_array($this->addPermission, ['all', 'added']));

        $existingUser = User::select('id')
            ->whereHas('roles', function ($q) {
                $q->where('name', 'client');
            })->where('company_id', company()->id)
            ->where('email', $request->client_email)
            ->whereNotNull('email')
            ->first();

        $leadContact = new Lead();
        $leadContact->company_id = company()->id;
        $leadContact->salutation = $request->salutation;
        $leadContact->client_name = $request->client_name;
        $leadContact->client_email = $request->client_email;
        $leadContact->note = trim_editor($request->note);
        $leadContact->source_id = $request->source_id;
        $leadContact->lead_project_id = $request->lead_project_id;
        $leadContact->client_id = $existingUser?->id;
        $leadContact->lead_owner = $request->lead_owner;
        $leadContact->company_name = $request->company_name;
        $leadContact->website = $request->website;
        $leadContact->address = $request->address;
        $leadContact->cell = $request->cell;
        $leadContact->office = $request->office;
        $leadContact->city = $request->city;
        $leadContact->state = $request->state;
        $leadContact->country = $request->country;
        $leadContact->postal_code = $request->postal_code;
        $leadContact->mobile = $request->mobile;

        if ($request->has('create_deal') && $request->create_deal == 'on') {
            Session::put('create_deal_with_lead', true);
            Session::put('deal_name', $request->name);
        }

        $leadContact->save();

        LeadTeamController::sync($leadContact, $request);

        if ($request->has('create_deal') && $request->create_deal == 'on') {
            $this->storeDeal($request, $leadContact);
        }

        // To add custom fields data
        if ($request->custom_fields_data) {
            $leadContact->updateCustomFieldData($request->custom_fields_data);
        }

        // Log search
        $this->logSearchEntry($leadContact->id, $leadContact->client_name, 'lead-contact.show', 'lead');

        if ($leadContact->client_email) {
            $this->logSearchEntry($leadContact->id, $leadContact->client_name, 'lead-contact.show', 'lead');
        }

        $redirectUrl = urldecode($request->redirect_url);

        if ($request->add_more == 'true') {
            $html = $this->create();

            return Reply::successWithData(__('messages.recordSaved'), ['html' => $html, 'add_more' => true]);
        }

        if ($redirectUrl == '') {
            $redirectUrl = route('lead-contact.index');
        }

        return Reply::successWithData(__('messages.recordSaved'), ['redirectUrl' => $redirectUrl]);

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $this->leadContact = Lead::with('leadSource', 'category')->findOrFail($id)->withCustomFields();
        // The lead's deal (if any) fills the Deal section, as on the create form; without one, "Create Deal" adds it
        $this->deal = Deal::with('products', 'leadAgent')->where('lead_id', $id)->orderBy('id')->first();

        $this->editPermission = user()->permission('edit_lead');

        abort_403(!($this->editPermission == 'all'
            || ($this->editPermission == 'added' && $this->leadContact->added_by == user()->id)
            || ($this->editPermission == 'owned' && $this->leadContact->lead_owner == user()->id)
            || ($this->editPermission == 'both' && $this->leadContact->added_by == user()->id) || user()->id == $this->leadContact->lead_owner)
        );

        $this->leadAgents = LeadAgent::with('user')->whereHas('user', function ($q) {
            $q->where('status', 'active');
        })->get();

        $getCustomFieldGroupsWithFields = $this->leadContact->getCustomFieldGroupsWithFields();
        $this->employees = User::allEmployees();

        $activeEmployees = $this->employees->filter(function ($employee) {
            return $employee->status !== 'deactive';
        });

        $selectedEmployee = $this->employees->firstWhere('id', $this->leadContact->lead_owner);

        if ($selectedEmployee && $selectedEmployee->status === 'deactive') {
            $this->employees = $activeEmployees->push($selectedEmployee);
        } else {
            $this->employees = $activeEmployees;
        }

        if ($getCustomFieldGroupsWithFields) {
            $this->fields = $getCustomFieldGroupsWithFields->fields;
        }

        $this->sources = LeadSource::all();
        $this->leadProjects = \App\Models\LeadProject::orderBy('name')->get();
        $this->categories = LeadCategory::all();
        $this->countries = countries();

        $this->pageTitle = __('modules.leadContact.updateTitle');
        $this->salutations = Salutation::cases();

        // Same Deal section as the create form
        $this->addPermission = $this->editPermission;
        $this->addDealPermission = user()->permission('add_deals');
        $this->canEditDeal = $this->deal && $this->canEditDeal($this->deal);
        $this->leadPipelines = LeadPipeline::orderBy('default', 'DESC')->get();
        $this->products = Product::all();
        $this->leadAgentArray = $this->leadAgents->pluck('user_id')->toArray();

        if (in_array(user()->id, $this->leadAgentArray)) {
            $this->myAgentId = $this->leadAgents->firstWhere('user_id', user()->id)->id;
        }

        // Assign Team: the lead's saved team, shown in the table and editable
        $this->departments = LeadTeamController::departments();
        $this->teamMembers = LeadTeamController::describe($this->leadContact->teamMembers()->pluck('users.id'));

        if (request()->ajax()) {
            $html = view('lead-contact.ajax.edit', $this->data)->render();

            return Reply::dataOnly(['status' => 'success', 'html' => $html, 'title' => $this->pageTitle]);
        }

        $this->view = 'lead-contact.ajax.edit';

        return view('lead-contact.create', $this->data);

    }

    /**
     * @param UpdateRequest $request
     * @param int $id
     * @return array|void
     * @throws \Froiden\RestAPI\Exceptions\RelatedResourceNotFoundException
     */
    public function update(UpdateRequest $request, $id)
    {
        $leadContact = Lead::findOrFail($id);
        $this->editPermission = user()->permission('edit_lead');

        abort_403(!($this->editPermission == 'all'
            || ($this->editPermission == 'added' && $leadContact->added_by == user()->id)
            || ($this->editPermission == 'owned' && $leadContact->lead_owner == user()->id)
            || ($this->editPermission == 'both' && $leadContact->added_by == user()->id) || user()->id == $leadContact->lead_owner)
        );

        $leadContact->salutation = $request->salutation;
        $leadContact->client_name = $request->client_name;
        $leadContact->client_email = $request->client_email;
        $leadContact->note = trim_editor($request->note);
        $leadContact->source_id = $request->source_id;
        $leadContact->lead_project_id = $request->lead_project_id;
        $leadContact->lead_owner = $request->lead_owner;
        $leadContact->category_id = $request->category_id;
        $leadContact->company_name = $request->company_name;
        $leadContact->website = $request->website;
        $leadContact->address = $request->address;
        $leadContact->cell = $request->cell;
        $leadContact->office = $request->office;
        $leadContact->city = $request->city;
        $leadContact->state = $request->state;
        $leadContact->country = $request->country;
        $leadContact->postal_code = $request->postal_code;
        $leadContact->mobile = $request->mobile;

        if ($request->filled('added_by') && $this->editPermission == 'all') {
            $leadContact->added_by = $request->added_by;
        }

        $leadContact->save();

        LeadTeamController::sync($leadContact, $request);

        // Deal section: update the lead's deal, or create one when it has none
        if ($request->create_deal == 'on') {
            $deal = Deal::where('lead_id', $leadContact->id)->orderBy('id')->first();

            if ($deal) {
                abort_403(!$this->canEditDeal($deal));
                $this->updateDeal($request, $deal);
            }
            else {
                $this->storeDeal($request, $leadContact);
            }
        }

        $clientCreated = $request->create_client == "on" ? '1' : '0';
        Deal::where('lead_id', $leadContact->id)->update(['create_client' => $clientCreated]);

        // To add custom fields data
        if ($request->custom_fields_data) {
            $leadContact->updateCustomFieldData($request->custom_fields_data);
        }

        return Reply::successWithData(__('messages.updateSuccess'), ['redirectUrl' => route('lead-contact.index')]);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $leadContact = Lead::findOrFail($id);
        $this->deletePermission = user()->permission('delete_lead');

        abort_403(!($this->deletePermission == 'all'
            || ($this->deletePermission == 'added' && $leadContact->added_by == user()->id)
            || ($this->deletePermission == 'owned' && $leadContact->lead_owner == user()->id)
            || ($this->deletePermission == 'both' && $leadContact->added_by == user()->id) || user()->id == $leadContact->lead_owner)
        );

        Lead::destroy($id);

        return Reply::success(__('messages.deleteSuccess'));

    }

    public function applyQuickAction(Request $request)
    {
        Lead::whereIn('id', explode(',', $request->row_ids))->delete();

        return Reply::success(__('messages.deleteSuccess'));
    }

    public function importLead()
    {
        $this->pageTitle = __('app.importExcel') . ' ' . __('app.menu.lead');

        $this->addPermission = user()->permission('add_lead');
        abort_403(!in_array($this->addPermission, ['all', 'added']));

        if (request()->ajax()) {
            $html = view('leads.ajax.import', $this->data)->render();

            return Reply::dataOnly(['status' => 'success', 'html' => $html, 'title' => $this->pageTitle]);
        }

        $this->view = 'leads.ajax.import';

        return view('leads.create', $this->data);
    }

    public function importStore(ImportRequest $request)
    {
        $rvalue = $this->importFileProcess($request, LeadImport::class);

        if($rvalue == 'abort'){
            return Reply::error(__('messages.abortAction'));
        }

        $view = view('leads.ajax.import_progress', $this->data)->render();

        return Reply::successWithData(__('messages.importUploadSuccess'), ['view' => $view]);
    }

    public function importProcess(ImportProcessRequest $request)
    {
        $batch = $this->importJobProcess($request, LeadImport::class, ImportLeadJob::class);

        return Reply::successWithData(__('messages.importProcessStart'), ['batch' => $batch]);
    }

    public function destroySession(){

        if (session()->has('is_imported')) {
            session()->forget('is_imported');
        }

        if (session()->has('leads')) {
            session()->forget('leads');
        }

        if (session()->has('leads_count')) {
            session()->forget('leads_count');
        }

        if(session()->has('total_leads')) {
            session()->forget('total_leads');
        }

        if(session()->has('create_deal_with_lead')) {
            session()->forget('create_deal_with_lead');
        }

        if(session()->has('deal_name')) {
            session()->forget('deal_name');
        }

        if(session()->has('duplicate_leads')) {
            session()->forget('duplicate_leads');
        }
    }

    /**
     * Same rule as DealController::update.
     */
    private function canEditDeal(Deal $deal): bool
    {
        $permission = user()->permission('edit_deals');
        $isAgentOrWatcher = (!is_null($deal->agent_id) && $deal->leadAgent?->user_id == user()->id)
            || (!is_null($deal->deal_watcher) && $deal->deal_watcher == user()->id);

        return $permission == 'all'
            || ($permission == 'added' && $deal->added_by == user()->id)
            || ($permission == 'owned' && $isAgentOrWatcher)
            || ($permission == 'both' && ($isAgentOrWatcher || $deal->added_by == user()->id));
    }

    /**
     * The lead edit form's Deal section, saved the way DealController::update saves a deal.
     */
    private function updateDeal($request, Deal $deal): void
    {
        $agentId = null;

        if (!is_null($request->agent_id)) {
            $agentId = LeadAgent::where('user_id', $request->agent_id)->where('lead_category_id', $request->category_id)->value('id');
        }

        $deal->name = $request->name;
        $deal->agent_id = $agentId;
        $deal->deal_watcher = $request->deal_watcher;
        $deal->lead_pipeline_id = $request->pipeline;
        $deal->pipeline_stage_id = $request->stage_id;
        $deal->close_date = companyToYmd($request->close_date);
        $deal->value = ($request->value) ?: 0;
        $deal->currency_id = $this->company->currency_id;
        $deal->category_id = $request->category_id;
        $deal->save();

        if ($request->has('product_id') || in_array('products', user_modules()) || in_array('purchase', user_modules())) {
            $deal->products()->sync((array) $request->product_id);
        }
    }

    public function storeDeal($request, $leadContact)
    {
        $this->addPermission = user()->permission('add_deals');
        abort_403(!in_array($this->addPermission, ['all', 'added']));
        $agentId = null;

        if (!is_null($request->agent_id)) {
            $leadAgent = LeadAgent::where('user_id', $request->agent_id)->where('lead_category_id', $request->category_id)->first();
            $agentId = isset($leadAgent) ? $leadAgent->id : null;
        }

        $deal = new Deal();
        $deal->name = $request->name;
        $deal->lead_id = $leadContact->id;
        $deal->next_follow_up = 'yes';
        $deal->category_id = $request->category_id;
        $deal->deal_watcher = $request->deal_watcher;
        $deal->lead_pipeline_id = $request->pipeline;
        $deal->pipeline_stage_id = $request->stage_id;
        $deal->create_client = $request->create_client == "on" ? '1' : '0';
        $deal->agent_id = $agentId;
        $deal->close_date = companyToYmd($request->close_date);
        $deal->value = ($request->value) ?: 0;
        $deal->currency_id = $this->company->currency_id;
        $deal->save();

        if (!is_null($request->product_id)) {

            $products = $request->product_id;

            foreach ($products as $product) {
                $leadProduct = new LeadProduct();
                $leadProduct->deal_id = $deal->id;
                $leadProduct->product_id = $product;
                $leadProduct->save();
            }
        }
    }

}
