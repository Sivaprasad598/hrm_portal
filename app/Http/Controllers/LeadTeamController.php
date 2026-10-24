<?php

namespace App\Http\Controllers;

use App\Models\EmployeeDetails;
use App\Models\Lead;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Lead contact form, "Assign Team": Department -> Designation -> Employee dropdowns, and saving the chosen
 * employees with the lead. Designations have no department of their own, so a department's designations are
 * the ones its active employees hold.
 */
class LeadTeamController extends AccountBaseController
{
    /**
     * Standalone Lead Team view (no tabs, no Create Meeting button)
     */
    public function showTeamOnly($id)
    {
        // Ensure the user has permission to view leads
        abort_403(user()->permission('view_lead') == 'none');

        $lead = Lead::findOrFail($id);

        // Fetch the team members using the existing static method
        $team = self::people($lead);

        return view('lead-contact.lead-team-only', [
            'lead' => $lead,
            'team' => $team,
            'pageTitle' => 'Lead Team'
        ]);
    }

    public function designations(Request $request)
    {
        $this->authorizeLeadForm();

        $designations = $this->activeEmployeeDetails()
            ->where('department_id', $request->integer('department_id'))
            ->whereNotNull('designation_id')
            ->with('designation:id,name')
            ->get()
            ->pluck('designation')
            ->filter()
            ->unique('id')
            ->sortBy(fn ($d) => mb_strtolower($d->name))
            ->values()
            ->map(fn ($d) => ['id' => $d->id, 'name' => $d->name]);

        return response()->json(['designations' => $designations]);
    }

    public function employees(Request $request)
    {
        $this->authorizeLeadForm();

        $userIds = $this->activeEmployeeDetails()
            ->where('department_id', $request->integer('department_id'))
            ->where('designation_id', $request->integer('designation_id'))
            ->pluck('user_id');

        return response()->json(['employees' => self::describe($userIds)]);
    }

    /**
     * Everyone who can be invited to a meeting with this lead: the lead contact, the lead owner and the lead team.
     * Someone who is both owner and team member is listed once. Keys ("lead", "user-<id>") are what the
     * Invite checkboxes post.
     */
    public static function people(Lead $lead): array
    {
        $people = [[
            'key' => 'lead',
            'user_id' => null,
            'role' => 'Lead',
            'name' => $lead->client_name,
            'designation' => $lead->company_name ? 'Lead contact · ' . $lead->company_name : 'Lead contact',
            'department' => '--',
            'email' => $lead->client_email ?: '--',
            'active' => true,
        ]];

        $ownerId = (int) $lead->lead_owner;
        $userIds = collect([$ownerId])->merge($lead->teamMembers()->pluck('users.id'))->filter()->unique();

        foreach (self::describe($userIds) as $person) {
            $isOwner = $person['id'] === $ownerId;
            $isMember = $lead->teamMembers->contains('id', $person['id']);

            $people[] = $person + [
                'key' => 'user-' . $person['id'],
                'user_id' => $person['id'],
                'role' => $isOwner && $isMember ? 'Lead Owner, Team Member' : ($isOwner ? 'Lead Owner' : 'Team Member'),
                'sort' => $isOwner ? 0 : 1,
            ];
        }

        // Lead first, then the owner, then team members by name
        return collect($people)->sortBy(fn ($p) => $p['key'] === 'lead' ? '0' : ($p['sort'] ?? 1) . mb_strtolower($p['name']))
            ->map(fn ($p) => array_diff_key($p, ['sort' => 1, 'id' => 1]))
            ->values()
            ->all();
    }

    /**
     * Edit form, "Add Employee": saves the employee to the lead's team straight away (an existing lead).
     * Adding someone already in the team changes nothing.
     */
    public function addMember(Request $request, $id)
    {
        $lead = $this->editableLead($id);

        $userId = User::withoutGlobalScope(\App\Scopes\ActiveScope::class)
            ->where('id', $request->integer('user_id'))
            ->where('company_id', company()->id)
            ->whereHas('employeeDetail')
            ->value('id');

        if (!$userId) {
            return response()->json(['message' => 'Select a valid employee.'], 422);
        }

        if ($lead->teamMembers()->where('users.id', $userId)->exists()) {
            return response()->json(['message' => 'This employee is already in the team.'], 422);
        }

        $lead->teamMembers()->attach($userId, ['added_by' => user()->id]);

        return response()->json(['member' => self::describe([$userId])[0]]);
    }

    /**
     * Edit form, "Remove": takes the employee off the lead's team straight away.
     */
    public function removeMember($id, $userId)
    {
        $this->editableLead($id)->teamMembers()->detach((int) $userId);

        return response()->json(['status' => 'success']);
    }

    /**
     * Same rule as LeadContactController::update.
     */
    private function editableLead($id): Lead
    {
        $lead = Lead::findOrFail($id);
        $permission = user()->permission('edit_lead');

        abort_403(!($permission == 'all'
            || ($permission == 'added' && $lead->added_by == user()->id)
            || ($permission == 'owned' && $lead->lead_owner == user()->id)
            || ($permission == 'both' && $lead->added_by == user()->id) || user()->id == $lead->lead_owner));

        return $lead;
    }

    /**
     * Meeting form, Invite list: the people of the lead picked in the form.
     */
    public function meetingPeople($id)
    {
        abort_403(user()->permission('view_lead') == 'none');

        return response()->json(['people' => self::people(Lead::findOrFail($id))]);
    }

    /**
     * Name, designation, department, email and phone for the team table, in the order given.
     */
    public static function describe($userIds): array
    {
        return User::withoutGlobalScope(\App\Scopes\ActiveScope::class)
            ->whereIn('id', collect($userIds)->all())
            ->with('employeeDetail.designation:id,name', 'employeeDetail.department:id,team_name')
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'designation' => $u->employeeDetail?->designation?->name ?? '--',
                'department' => $u->employeeDetail?->department?->team_name ?? '--',
                'email' => $u->email ?? '--',
                'phone' => $u->mobile ? trim(($u->country_phonecode ? '+' . $u->country_phonecode . ' ' : '') . $u->mobile) : '--',
                'active' => $u->status === 'active',
            ])
            ->all();
    }

    /**
     * Saves the lead's team from the form. Unticking "Assign Team" clears it; repeated ids are saved once,
     * and anyone who is not an employee of this company is ignored.
     */
    public static function sync(Lead $lead, Request $request): void
    {
        if ($request->assign_team !== 'on') {
            $lead->teamMembers()->detach();

            return;
        }

        $ids = collect((array) $request->team_members)->map(fn ($id) => (int) $id)->filter()->unique();

        $valid = User::withoutGlobalScope(\App\Scopes\ActiveScope::class)
            ->whereIn('id', $ids)
            ->where('company_id', company()->id)
            ->whereHas('employeeDetail')
            ->pluck('id');

        // Members already on the lead keep who added them; new ones are added by the current user
        $lead->teamMembers()->sync($valid->mapWithKeys(fn ($id) => [$id => ['added_by' => user()->id]])->all());
    }

    public static function departments()
    {
        return Team::orderBy('team_name')->get(['id', 'team_name']);
    }

    private function activeEmployeeDetails()
    {
        return EmployeeDetails::whereHas('user', fn ($q) => $q->where('status', 'active'));
    }

    private function authorizeLeadForm(): void
    {
        abort_403(!in_array(user()->permission('add_lead'), ['all', 'added'])
            && !in_array(user()->permission('edit_lead'), ['all', 'added', 'owned', 'both']));
    }
}
