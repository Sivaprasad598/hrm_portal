<?php

namespace App\Services;

use App\Models\CustomerMeeting;
use App\Models\LeadTeamMeeting;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Lead Team Meetings: project qualification from the meeting transcript (OpenAI, same key as the notetaker bot).
 *
 * Validate Fit is judged against what the lead team can deliver: the departments and designations of the
 * employees assigned to the lead (Assign Team). A GIS Department team and a customer who needs GIS mapping
 * is a High Fit; a customer who needs food delivery is a Low Fit.
 */
class LeadMeetingQualifier
{

    private const API = 'https://api.openai.com/v1/chat/completions';

    public static function analyse(CustomerMeeting $meeting): LeadTeamMeeting
    {
        $meeting->loadMissing(['lead', 'leadProject']);

        if (!$meeting->lead) {
            throw new RuntimeException('This meeting has no lead.');
        }

        // The transcript is the source; the AI notes are used when a meeting has notes but no transcript
        $source = trim((string) ($meeting->meet_transcript ?: $meeting->meet_notes));

        if ($source === '') {
            throw new RuntimeException('This meeting has no transcript or notes yet. They are made after the meeting is recorded.');
        }

        if (!config('services.openai.key')) {
            throw new RuntimeException('OpenAI is not configured (OPENAI_API_KEY in .env).');
        }

        $capabilities = self::teamCapabilities($meeting);
        $result = self::ask($meeting, $source, $capabilities);

        $fit = strtolower((string) ($result['validate_fit'] ?? ''));

        $record = LeadTeamMeeting::firstOrNew(['customer_meeting_id' => $meeting->id]);
        $record->fill([
            'company_id' => $meeting->lead->company_id,
            'lead_id' => $meeting->lead_id,
            'validate_fit' => array_key_exists($fit, LeadTeamMeeting::FITS) ? $fit : null,
            'fit_reason' => self::text($result['fit_reason'] ?? null),
            'service_requirements' => self::list($result['service_requirements'] ?? []),
            'buying_entity' => self::text($result['buying_entity'] ?? null),
            'timing' => self::text($result['timing'] ?? null),
            'decision_makers' => self::list($result['decision_makers'] ?? []),
            'team_capabilities' => $capabilities,
            'source' => 'ai',
            'analysed_at' => now(),
        ])->save();

        return $record;
    }

    /**
     * Departments with the designations of the lead's team (and owner), e.g. "GIS Department: GIS Analyst, GIS Team Lead".
     */
    public static function teamCapabilities(CustomerMeeting $meeting): array
    {
        $lead = $meeting->lead;
        $ids = $lead->teamMembers()->pluck('users.id')->push($lead->lead_owner)->filter()->unique();

        return User::withoutGlobalScope(\App\Scopes\ActiveScope::class)
            ->whereIn('id', $ids)
            ->with('employeeDetail.designation:id,name', 'employeeDetail.department:id,team_name')
            ->get()
            ->groupBy(fn ($u) => $u->employeeDetail?->department?->team_name ?? 'No department')
            ->map(fn ($users) => $users->map(fn ($u) => $u->employeeDetail?->designation?->name)->filter()->unique()->values()->all())
            ->all();
    }

    private static function ask(CustomerMeeting $meeting, string $source, array $capabilities): array
    {
        $team = collect($capabilities)->map(fn ($designations, $department) => '- ' . $department
            . ($designations ? ': ' . implode(', ', $designations) : ''))->implode("\n") ?: '- (no team assigned)';

        $context = implode("\n", array_filter([
            'Lead: ' . ($meeting->lead->client_name ?? '-') . ($meeting->lead->company_name ? ' (' . $meeting->lead->company_name . ')' : ''),
            'Project: ' . ($meeting->leadProject->name ?? '-'),
            'Meeting: ' . ($meeting->title ?: ($meeting->purpose ?: 'Meeting')),
            $meeting->description ? 'Agenda: ' . $meeting->description : null,
        ]));

        $response = Http::withToken(config('services.openai.key'))
            ->withHeaders(array_filter([
                'OpenAI-Organization' => config('services.openai.organization'),
                'OpenAI-Project' => config('services.openai.project'),
            ]))
            ->timeout(120)
            ->retry(2, 2000, throw: false)
            ->post(self::API, [
                'model' => config('services.openai.notes_model'),
                'temperature' => 0.1,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => "You qualify sales leads for our company from a meeting transcript (or notes) with a customer.\n"
                            . "What our company can deliver is defined by the departments and designations of the team assigned to this lead:\n{$team}\n\n"
                            . "Reply with ONLY a JSON object with these keys:\n"
                            . "validate_fit: \"high\" if the customer's project matches what that team does, \"medium\" if it looks relevant but details need checking, \"low\" if it does not match.\n"
                            . "fit_reason: one short sentence explaining the fit by naming the matching (or missing) department/skill.\n"
                            . "service_requirements: array of short items of work the customer needs.\n"
                            . "buying_entity: the organization paying for the work (company, its department, or government body).\n"
                            . "timing: deadlines, start dates or durations mentioned.\n"
                            . "decision_makers: array of people or roles who approve or choose the vendor: \"Name - Role\" when both are known, "
                            . "otherwise just the name or just the role (e.g. \"Project Director\"). Never write the word \"Role\" or \"Name\" as a placeholder.\n"
                            . "Write in English. Only use facts from the text; for anything not mentioned use \"Not discussed\" (or an empty array). "
                            . "Never invent names, dates or amounts; the transcript has recognition errors and no speaker names.",
                    ],
                    ['role' => 'user', 'content' => "Meeting details:\n{$context}\n\nTranscript:\n{$source}"],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Qualification failed (' . $response->status() . '): ' . ($response->json('error.message') ?: 'OpenAI error'));
        }

        $data = json_decode((string) $response->json('choices.0.message.content'), true);

        if (!is_array($data)) {
            throw new RuntimeException('Qualification failed: the model did not return JSON.');
        }

        return $data;
    }

    private static function text($value): ?string
    {
        $value = trim(is_array($value) ? implode(', ', $value) : (string) $value);

        return $value === '' || strcasecmp($value, 'Not discussed') === 0 ? null : mb_substr($value, 0, 2000);
    }

    private static function list($value): array
    {
        return collect(is_array($value) ? $value : [$value])
            ->map(fn ($v) => trim(is_array($v) ? implode(' - ', array_filter($v)) : (string) $v))
            // "Project Director - Role" / "Name - Project Director": drop the placeholder half
            ->map(fn ($v) => trim(preg_replace('/^(name|unknown)\s*-\s*|\s*-\s*(role|name|unknown)$/i', '', $v)))
            ->filter(fn ($v) => $v !== '' && strcasecmp($v, 'Not discussed') !== 0)
            ->take(20)->values()->all();
    }

}
