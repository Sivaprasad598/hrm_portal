<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Models\CustomerMeeting;
use App\Models\LeadTeamMeeting;
use App\Services\LeadMeetingQualifier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Lead Team Meeting notes (lead_team_meetings): run the qualification again, or correct it by hand.
 */
class LeadMeetingController extends AccountBaseController
{

    public function analyse($meetingId)
    {
        $meeting = $this->teamMeeting($meetingId);

        try {
            $record = LeadMeetingQualifier::analyse($meeting);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'fail', 'message' => $e->getMessage()], 422);
        }

        return Reply::successWithData('Lead meeting notes updated from the transcript.', ['notes' => $this->present($record)]);
    }

    public function update(Request $request, $meetingId)
    {
        $meeting = $this->teamMeeting($meetingId);

        $request->validate([
            'validate_fit' => ['nullable', Rule::in(array_keys(LeadTeamMeeting::FITS))],
            'fit_reason' => 'nullable|string|max:2000',
            'service_requirements' => 'nullable|string|max:4000',
            'buying_entity' => 'nullable|string|max:2000',
            'timing' => 'nullable|string|max:2000',
            'decision_makers' => 'nullable|string|max:4000',
        ]);

        // One item per line in the two list fields
        $lines = fn ($text) => collect(preg_split('/\r\n|\r|\n/', (string) $text))->map(fn ($l) => trim($l, " \t-•"))->filter()->values()->all();

        $record = LeadTeamMeeting::firstOrNew(['customer_meeting_id' => $meeting->id]);
        $record->fill([
            'company_id' => $meeting->lead->company_id,
            'lead_id' => $meeting->lead_id,
            'validate_fit' => $request->validate_fit ?: null,
            'fit_reason' => $request->fit_reason,
            'service_requirements' => $lines($request->service_requirements),
            'buying_entity' => $request->buying_entity,
            'timing' => $request->timing,
            'decision_makers' => $lines($request->decision_makers),
            'source' => 'manual',
            'updated_by' => user()->id,
        ])->save();

        return Reply::successWithData('Lead meeting notes saved.', ['notes' => $this->present($record)]);
    }

    private function teamMeeting($meetingId): CustomerMeeting
    {
        $meeting = CustomerMeeting::with(['lead', 'attendees', 'leadTeamMeeting'])->findOrFail($meetingId);

        abort_403(!in_array(user()->permission('view_lead'), ['all', 'added', 'owned', 'both']));
        abort_if(!$meeting->isLeadTeamMeeting(), 404, 'Not a Lead Team meeting.');

        return $meeting;
    }

    private function present(LeadTeamMeeting $record): array
    {
        return [
            'html' => view('lead-contact.partials.lead-meeting-notes', ['leadMeeting' => $record, 'meeting' => $record->meeting])->render(),
        ];
    }

}
