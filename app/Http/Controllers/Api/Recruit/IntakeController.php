<?php

namespace App\Http\Controllers\Api\Recruit;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\JobPosition;
use App\Models\RecruitInbound;
use App\Models\RecruitSetting;
use App\Services\Recruit\CandidateIntake;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * The website's "join us" form posts here (arowanasoft.com, by curl):
 *
 *   POST /api/recruit/v1/applications   a candidate's details and resume
 *   GET  /api/recruit/v1/positions      the open positions, for the form's dropdown
 *
 * Authenticated by RecruitApiKey. Every submission is kept in recruit_inbound
 * as it arrived; the same submission sent twice on one day is answered with
 * the first one's reference and not imported again.
 */
class IntakeController extends Controller
{

    public function store(Request $request, CandidateIntake $intake): JsonResponse
    {
        /** @var RecruitSetting $setting */
        $setting = $request->attributes->get('recruit_setting');

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:191',
            'email' => 'required|email|max:191',
            'mobile' => 'nullable|string|max:20',
            'position' => 'nullable|string|max:191',
            'qualification' => 'nullable|string|max:191',
            'total_experience' => 'nullable|numeric|min:0|max:60',
            'current_location' => 'nullable|string|max:191',
            'current_company' => 'nullable|string|max:191',
            'current_designation' => 'nullable|string|max:191',
            'current_ctc' => 'nullable|numeric|min:0',
            'expected_ctc' => 'nullable|numeric|min:0',
            'notice_period_days' => 'nullable|integer|min:0|max:365',
            'skills' => 'nullable',
            'message' => 'nullable|string|max:10000',
            'resume' => 'nullable|file|mimes:pdf,doc,docx|max:5120',
            'resume_base64' => 'nullable|string|max:7500000',
            'resume_name' => 'nullable|required_with:resume_base64|string|max:150',
        ], [
            'resume.mimes' => 'The resume must be a PDF or Word file.',
            'resume.max' => 'The resume can be at most 5 MB.',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $fields = $request->only(['name', 'email', 'mobile', 'position', 'qualification', 'total_experience', 'current_location',
            'current_company', 'current_designation', 'current_ctc', 'expected_ctc', 'notice_period_days', 'skills', 'message']);
        $externalId = sha1(json_encode([strtolower((string) $request->email), $request->mobile, $request->position, $request->message, now()->toDateString()]));

        $earlier = RecruitInbound::withoutGlobalScopes()->where('company_id', $setting->company_id)->where('channel', 'api')
            ->where('external_id', $externalId)->first();

        if ($earlier) {
            return response()->json(['status' => 'success', 'reference' => $this->reference($earlier), 'duplicate' => true,
                'message' => 'This application was already received today.']);
        }

        $files = [];

        if ($request->hasFile('resume')) {
            $file = $request->file('resume');
            $files[] = ['name' => $file->getClientOriginalName(), 'content' => (string) file_get_contents($file->getRealPath()), 'mime' => $file->getMimeType()];
        } elseif ($request->filled('resume_base64')) {
            $content = base64_decode(preg_replace('/^data:[^,]+,/', '', (string) $request->resume_base64), true);
            $ext = strtolower(pathinfo((string) $request->resume_name, PATHINFO_EXTENSION));

            if ($content === false || !in_array($ext, CandidateIntake::RESUME_EXTENSIONS, true) || strlen($content) > CandidateIntake::MAX_FILE_BYTES) {
                return response()->json(['status' => 'error', 'message' => 'resume_base64 must be a PDF or Word file (named in resume_name) of at most 5 MB.'], 422);
            }

            $files[] = ['name' => $request->resume_name, 'content' => $content, 'mime' => null];
        }

        $record = new RecruitInbound([
            'company_id' => $setting->company_id,
            'channel' => 'api',
            'external_id' => $externalId,
            'from_email' => mb_substr(strtolower((string) $request->email), 0, 191),
            'from_name' => mb_substr((string) $request->name, 0, 191),
            'subject' => mb_substr('Website application' . ($request->position ? ': ' . $request->position : ''), 0, 500),
            'body' => $request->message,
            'payload' => $fields + ['resume' => $files ? $files[0]['name'] : null],
            'ip' => $request->ip(),
            'received_at' => now(),
        ]);

        try {
            $outcome = $intake->receive($fields + ['source_detail' => 'Website form'], $files, 'website', $setting->company_id);
        } catch (\Throwable $e) {
            logger()->error('Website application import failed', ['error' => $e->getMessage()]);
            $record->status = 'failed';
            $record->reason = mb_substr($e->getMessage(), 0, 500);
            $record->save();

            return response()->json(['status' => 'error', 'message' => 'The application could not be saved. Please try again later.'], 500);
        }

        $record->status = $outcome['status'];
        $record->candidate_id = $outcome['candidate']->id;
        $record->job_application_id = $outcome['application']->id;
        $record->reason = $outcome['position_matched'] ? null : ($request->position ? 'Position "' . $request->position . '" not found among the open ones - waiting under Unassigned.' : null);
        $record->save();

        // Reading the resume with AI can take a minute: after the website has its answer.
        $candidateId = $outcome['candidate']->id;
        dispatch(function () use ($candidateId) {
            if ($candidate = Candidate::withoutGlobalScopes()->find($candidateId)) {
                app(CandidateIntake::class)->aiFill($candidate);
            }
        })->afterResponse();

        return response()->json([
            'status' => 'success',
            'reference' => $this->reference($record),
            'position_matched' => $outcome['position_matched'],
            'message' => 'Thank you for your interest. We have received your application.',
        ], 201);
    }

    public function positions(Request $request): JsonResponse
    {
        $setting = $request->attributes->get('recruit_setting');

        $positions = JobPosition::withoutGlobalScopes()->with(['department' => fn ($q) => $q->withoutGlobalScopes(), 'branch' => fn ($q) => $q->withoutGlobalScopes()])
            ->where('company_id', $setting->company_id)->where('status', 'open')
            ->where(fn ($q) => $q->whereNull('closing_date')->orWhereDate('closing_date', '>=', now()->toDateString()))
            ->orderBy('title')->get()
            ->map(fn (JobPosition $p) => [
                'code' => $p->code,
                'title' => $p->title,
                'department' => $p->department?->team_name,
                'location' => $p->location ?: $p->branch?->branch_name,
                'type' => JobPosition::TYPES[$p->employment_type] ?? $p->employment_type,
                'experience' => $p->experienceText(),
                'openings' => $p->openings,
                'skills' => $p->skills,
                'description' => $p->description,
                'closing_date' => $p->closing_date?->toDateString(),
            ]);

        return response()->json(['status' => 'success', 'positions' => $positions]);
    }

    private function reference(RecruitInbound $record): string
    {
        return 'APP-' . str_pad((string) $record->id, 6, '0', STR_PAD_LEFT);
    }

}
