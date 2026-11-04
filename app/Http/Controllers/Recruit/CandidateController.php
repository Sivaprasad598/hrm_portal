<?php

namespace App\Http\Controllers\Recruit;

use App\Helper\Files;
use App\Helper\Reply;
use App\Models\AiSetting;
use App\Models\Candidate;
use App\Models\CandidateFile;
use App\Models\JobApplication;
use App\Models\JobPosition;
use App\Models\RecruitInterview;
use App\Models\RecruitSetting;
use App\Services\Ai\AiClient;
use App\Services\Ai\ResumeExtractor;
use App\Services\Recruit\CandidateConversion;
use App\Services\Recruit\CandidateIntake;
use App\Services\Recruit\Pipeline;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Recruitment -> Resumes: the talent pool. Every candidate, whatever they
 * applied for or whether they applied at all, with their profile, files,
 * applications, interviews, offers and timeline.
 */
class CandidateController extends RecruitBaseController
{

    public function index(Request $request)
    {
        $this->needView();
        $this->pageTitle = 'Resumes';

        $this->candidates = Candidate::with(['resume', 'applications.position'])
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")
                ->orWhere('email', 'like', "%$s%")->orWhere('mobile', 'like', "%$s%")->orWhere('skills', 'like', "%$s%")
                ->orWhere('qualification', 'like', "%$s%")->orWhere('current_company', 'like', "%$s%")
                ->orWhere('current_designation', 'like', "%$s%")->orWhere('current_location', 'like', "%$s%")))
            ->when($request->source, fn ($q, $s) => $q->where('source', $s))
            ->when($request->filled('exp_min'), fn ($q) => $q->where('total_experience', '>=', (float) $request->exp_min))
            ->when($request->filled('exp_max'), fn ($q) => $q->where('total_experience', '<=', (float) $request->exp_max))
            ->when($request->position_id, fn ($q, $p) => $q->whereHas('applications', fn ($a) => $a->where('job_position_id', $p)))
            ->when($request->status === 'active', fn ($q) => $q->whereHas('applications', fn ($a) => $a->whereIn('stage', JobApplication::ACTIVE)))
            ->when($request->status === 'pool', fn ($q) => $q->whereNull('user_id')->whereDoesntHave('applications', fn ($a) => $a->whereIn('stage', JobApplication::ACTIVE)))
            ->when($request->status === 'joined', fn ($q) => $q->whereNotNull('user_id'))
            ->orderByDesc('id')
            ->paginate(30)->withQueryString();

        $this->positions = JobPosition::orderByDesc('id')->get(['id', 'title', 'code', 'status']);

        return view('recruitment.candidates.index', $this->data);
    }

    public function create(Request $request)
    {
        $this->needManage();
        $this->candidate = new Candidate(['source' => 'manual']);
        $this->positions = JobPosition::whereIn('status', ['open', 'on_hold'])->orderBy('title')->get();
        $this->positionId = (int) $request->position_id;
        $this->aiReady = AiSetting::forCompany(company()->id)->ready();

        return $this->panel('recruitment.candidates.form', 'Add Candidate');
    }

    public function store(Request $request, CandidateIntake $intake)
    {
        $this->needManage();
        $data = $this->validated($request);

        if ($existing = $intake->findExisting($intake->email($data['email'] ?? null), $intake->mobile($data['mobile'] ?? null), company()->id)) {
            return Reply::error($existing->name . ' is already in the talent pool with this email or mobile. Open their profile and add them to the position from there.');
        }

        $files = [];

        if ($request->hasFile('resume_file')) {
            $file = $request->file('resume_file');
            $files[] = ['name' => $file->getClientOriginalName(), 'content' => (string) file_get_contents($file->getRealPath()), 'mime' => $file->getMimeType()];
        }

        $outcome = $intake->receive($data + ['position' => $request->job_position_id ?: null, 'message' => $request->cover_note],
            $files, $request->source ?: 'manual', company()->id);

        $candidate = $outcome['candidate'];
        $candidate->fill(array_intersect_key($data, array_flip(['gender', 'date_of_birth', 'address', 'notes', 'source_detail'])));
        $candidate->work_experience = $this->workExperience($request) ?: $candidate->work_experience;
        $candidate->save();

        if ($request->boolean('ai_filled')) {
            $candidate->ai_filled_at = now();
            $candidate->save();
        }

        return Reply::successWithData('Candidate added.', ['redirectUrl' => route('candidates.show', $candidate->id)]);
    }

    public function show($id)
    {
        $this->needView();
        $this->candidate = Candidate::with(['files', 'employee', 'logs.doneBy', 'logs.application.position'])->findOrFail($id);
        $this->pageTitle = $this->candidate->name;

        $this->applications = $this->candidate->applications()->with(['position', 'offers.designation', 'offers.signedFile',
            'interviews.feedback.user'])->get();
        $this->openPositions = JobPosition::whereIn('status', ['open', 'on_hold'])
            ->whereNotIn('id', $this->applications->whereNotIn('stage', ['joined', 'rejected', 'withdrawn'])->pluck('job_position_id')->filter())
            ->orderBy('title')->get();
        $this->inbound = \App\Models\RecruitInbound::where('candidate_id', $this->candidate->id)->orderByDesc('id')->get();
        $this->conversionProblem = CandidateConversion::problem($this->candidate);
        $this->aiReady = AiSetting::forCompany(company()->id)->ready();
        $this->setting = RecruitSetting::forCompany(company()->id);

        return view('recruitment.candidates.show', $this->data);
    }

    public function edit($id)
    {
        $this->needManage();
        $this->candidate = Candidate::findOrFail($id);
        $this->positions = collect();
        $this->positionId = 0;
        $this->aiReady = AiSetting::forCompany(company()->id)->ready();

        return $this->panel('recruitment.candidates.form', 'Edit Candidate — ' . $this->candidate->name);
    }

    public function update(Request $request, $id, CandidateIntake $intake, Pipeline $pipeline)
    {
        $this->needManage();
        $candidate = Candidate::findOrFail($id);
        $data = $this->validated($request, $candidate);

        $email = $intake->email($data['email'] ?? null);
        $mobile = $intake->mobile($data['mobile'] ?? null);
        $other = $intake->findExisting($email, $mobile, company()->id);

        if ($other && $other->id !== $candidate->id) {
            return Reply::error($other->name . ' already has this email or mobile in the talent pool.');
        }

        $data['email'] = $email;
        $data['mobile'] = $mobile;
        $data['skills'] = $intake->skills($request->skills);
        $data['work_experience'] = $this->workExperience($request);
        $candidate->fill($data);
        $changed = array_keys($candidate->getDirty());
        $candidate->save();

        if ($request->hasFile('resume_file')) {
            $intake->storeUploaded($candidate, $request->file('resume_file'), 'resume', 'Resume (' . now()->format('d M Y') . ')');
            $changed[] = 'resume';
        }

        if ($changed) {
            $pipeline->log($candidate->id, null, 'updated', 'Changed: ' . str_replace('_', ' ', implode(', ', $changed)));
        }

        return Reply::successWithData('Candidate updated.', ['redirectUrl' => route('candidates.show', $candidate->id)]);
    }

    public function destroy($id)
    {
        $this->needManage();
        $candidate = Candidate::with('applications')->findOrFail($id);
        $applicationIds = $candidate->applications->pluck('id');

        if ($candidate->user_id || RecruitInterview::whereIn('job_application_id', $applicationIds)->exists()
            || \App\Models\JobOffer::whereIn('job_application_id', $applicationIds)->exists()) {
            return Reply::error('This candidate has interviews, offers or has joined, so the record is kept. Reject or withdraw the application instead.');
        }

        foreach ($candidate->files as $file) {
            Files::deleteFile($file->hashname, CandidateFile::FILE_PATH . '/' . $candidate->id);
        }

        $candidate->delete();

        return Reply::successWithData('Candidate deleted.', ['redirectUrl' => route('candidates.index')]);
    }

    /** Read a resume PDF on the Add / Edit form, before saving. Nothing is saved. */
    public function aiPreview(Request $request)
    {
        $this->needManage();
        $request->validate(['resume_file' => 'required|file|mimes:pdf|max:5120'], [
            'resume_file.mimes' => 'AI can read a PDF resume only.', 'resume_file.max' => 'The resume can be at most 5 MB.',
        ]);

        $setting = AiSetting::forCompany(company()->id);

        if (!$setting->ready()) {
            return Reply::error('AI is not set up. Enable it and add an API key on Settings → AI Settings.');
        }

        try {
            $data = (new ResumeExtractor(new AiClient($setting)))->extract($request->file('resume_file'));
        } catch (\Throwable $e) {
            return Reply::error('The resume could not be read: ' . $e->getMessage());
        }

        return Reply::dataOnly(['status' => 'success', 'data' => $data, 'provider' => AiSetting::PROVIDERS[$setting->provider]]);
    }

    /** Read the saved resume with AI into the empty fields. */
    public function aiFill($id, CandidateIntake $intake)
    {
        $this->needManage();
        $candidate = Candidate::findOrFail($id);

        if (!AiSetting::forCompany(company()->id)->ready()) {
            return Reply::error('AI is not set up. Enable it and add an API key on Settings → AI Settings.');
        }

        $result = $intake->aiFill($candidate, true);

        if ($result === null) {
            return Reply::error('There is no PDF resume to read for this candidate.');
        }

        if ($result === 'failed') {
            return Reply::error('The resume could not be read with AI. The reason is on the timeline.');
        }

        return Reply::successWithData($result, ['redirectUrl' => route('candidates.show', $candidate->id)]);
    }

    public function uploadFile(Request $request, $id, CandidateIntake $intake, Pipeline $pipeline)
    {
        $this->needManage();
        $candidate = Candidate::findOrFail($id);
        $request->validate([
            'file' => 'required|file|mimes:' . implode(',', CandidateIntake::FILE_EXTENSIONS) . '|max:5120',
            'category' => ['required', Rule::in(['resume', 'document'])],
            'name' => 'nullable|string|max:150',
        ], ['file.required' => 'Choose the file.', 'file.mimes' => 'PDF, Word or image files only.', 'file.max' => 'At most 5 MB.']);

        $file = $intake->storeUploaded($candidate, $request->file('file'), $request->category, $request->name ?: null);
        $pipeline->log($candidate->id, null, 'file', $file->name);

        return Reply::successWithData('File added.', ['redirectUrl' => route('candidates.show', $candidate->id)]);
    }

    public function deleteFile($fileId, Pipeline $pipeline)
    {
        $this->needManage();
        $file = CandidateFile::findOrFail($fileId);

        if (\App\Models\JobOffer::where('signed_file_id', $file->id)->exists()) {
            return Reply::error('This is the signed offer kept as proof of acceptance; it cannot be removed.');
        }

        Files::deleteFile($file->hashname, CandidateFile::FILE_PATH . '/' . $file->candidate_id);
        $pipeline->log($file->candidate_id, null, 'file', 'Removed: ' . $file->name);
        $file->delete();

        return Reply::success('File removed.');
    }

    public function note(Request $request, $id, Pipeline $pipeline)
    {
        $this->needManage();
        $candidate = Candidate::findOrFail($id);
        $request->validate(['note' => 'required|string|max:5000', 'job_application_id' => 'nullable|integer']);

        $applicationId = $request->job_application_id
            ? JobApplication::where('candidate_id', $candidate->id)->findOrFail($request->job_application_id)->id : null;
        $pipeline->log($candidate->id, $applicationId, 'note', trim($request->note));

        return Reply::successWithData('Note added.', ['redirectUrl' => route('candidates.show', $candidate->id)]);
    }

    /** Convert to Employee: the Add Employee form, filled from the candidate. */
    public function convert($id)
    {
        $this->needManage();
        $candidate = Candidate::findOrFail($id);

        if ($problem = CandidateConversion::problem($candidate)) {
            return redirect()->route('candidates.show', $candidate->id)->with('recruit_error', $problem);
        }

        return redirect()->route('employees.create', ['candidate' => $candidate->id]);
    }

    private function validated(Request $request, ?Candidate $candidate = null): array
    {
        $request->validate([
            'name' => 'required|string|max:191',
            'email' => 'nullable|email|max:191|required_without:mobile',
            'mobile' => 'nullable|string|max:20|required_without:email',
            'gender' => 'nullable|in:male,female,others',
            'address' => 'nullable|string|max:1000',
            'current_location' => 'nullable|string|max:191',
            'qualification' => 'nullable|string|max:191',
            'total_experience' => 'nullable|numeric|min:0|max:60',
            'current_company' => 'nullable|string|max:191',
            'current_designation' => 'nullable|string|max:191',
            'current_ctc' => 'nullable|numeric|min:0',
            'expected_ctc' => 'nullable|numeric|min:0',
            'notice_period_days' => 'nullable|integer|min:0|max:365',
            'source' => ['nullable', Rule::in(array_keys(Candidate::SOURCES))],
            'source_detail' => 'nullable|string|max:191',
            'notes' => 'nullable|string|max:5000',
            'resume_file' => 'nullable|file|mimes:pdf,doc,docx|max:5120',
            'job_position_id' => 'nullable|integer',
        ], [
            'email.required_without' => 'Give an email or a mobile number.',
            'mobile.required_without' => 'Give an email or a mobile number.',
            'resume_file.mimes' => 'The resume must be a PDF or Word file.',
        ]);

        $data = $request->only(['name', 'email', 'mobile', 'gender', 'address', 'current_location', 'qualification', 'total_experience',
            'current_company', 'current_designation', 'current_ctc', 'expected_ctc', 'notice_period_days', 'source_detail', 'notes']);

        foreach (['total_experience', 'current_ctc', 'expected_ctc', 'notice_period_days', 'gender'] as $key) {
            $data[$key] = $request->filled($key) ? $request->input($key) : null;
        }

        $data['date_of_birth'] = $this->date($request->date_of_birth, 'date of birth', false);

        if ($candidate) {
            $data['source'] = $request->source ?: $candidate->source;
        }

        $data['skills'] = $request->skills;

        return $data;
    }

    /** The work history rows on the form, in the ResumeExtractor shape. */
    private function workExperience(Request $request): array
    {
        $rows = [];

        foreach ((array) $request->input('exp_company', []) as $i => $company) {
            if (trim((string) $company) === '') {
                continue;
            }

            $rows[] = [
                'company_name' => mb_substr(trim($company), 0, 100),
                'location' => mb_substr(trim((string) ($request->input('exp_location')[$i] ?? '')), 0, 150) ?: null,
                'designation' => mb_substr(trim((string) ($request->input('exp_designation')[$i] ?? '')), 0, 100) ?: null,
                'joining_date' => trim((string) ($request->input('exp_joining')[$i] ?? '')) ?: null,
                'leaving_date' => trim((string) ($request->input('exp_leaving')[$i] ?? '')) ?: null,
            ];
        }

        return array_slice($rows, 0, 15);
    }

}
