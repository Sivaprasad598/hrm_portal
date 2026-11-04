<?php

namespace App\Http\Controllers\Recruit;

use App\Helper\Reply;
use App\Models\Branch;
use App\Models\Designation;
use App\Models\JobApplication;
use App\Models\JobOffer;
use App\Models\Team;
use App\Services\Recruit\CandidateIntake;
use App\Services\Recruit\Pipeline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Offers: drafted for a Selected candidate, sent, then accepted (with proof:
 * the signed copy or a note of how the acceptance came in), declined or
 * withdrawn. Each step moves the application (Offered, Offer Accepted ...)
 * and is kept on the timeline. The letter itself is the company's offer
 * letter, filled from the offer (offer-letter.php?offer_id=).
 */
class JobOfferController extends RecruitBaseController
{

    /** What can follow each status. */
    private const NEXT = [
        'draft' => ['sent', 'withdrawn'],
        'sent' => ['accepted', 'declined', 'withdrawn'],
        'accepted' => ['withdrawn'],
    ];

    public function create(Request $request)
    {
        $this->needManage();
        $this->application = JobApplication::with('candidate', 'position', 'offer')->findOrFail($request->application);

        if ($this->application->stage !== 'selected') {
            return Reply::error('An offer is made once the candidate is Selected (now: ' . $this->application->stageLabel() . ').');
        }

        $position = $this->application->position;
        $candidate = $this->application->candidate;
        $this->offer = new JobOffer([
            'reference_no' => JobOffer::nextReference(),
            'designation_id' => $position?->designation_id,
            'department_id' => $position?->department_id,
            'branch_id' => $position?->branch_id,
            'annual_ctc' => $candidate->expected_ctc ?: ($position?->salary_max ?: null),
            'offer_date' => now(),
            'joining_date' => now()->addDays(max(15, (int) $candidate->notice_period_days)),
            'valid_until' => now()->addDays(7),
        ]);
        $this->lookups();

        return $this->panel('recruitment.offers.form', 'Make Offer — ' . $candidate->name);
    }

    public function store(Request $request, Pipeline $pipeline)
    {
        $this->needManage();
        $application = JobApplication::with('candidate')->findOrFail($request->job_application_id);

        if ($application->stage !== 'selected') {
            return Reply::error('An offer is made once the candidate is Selected.');
        }

        $offer = JobOffer::create($this->validated($request) + [
            'company_id' => company()->id, 'job_application_id' => $application->id, 'status' => 'draft', 'created_by' => user()->id,
        ]);

        $pipeline->log($application->candidate_id, $application->id, 'offer', 'Offer drafted: ' . $this->summary($offer));

        return Reply::successWithData('Offer drafted. Open the letter to print it, then mark it sent.',
            ['redirectUrl' => route('candidates.show', $application->candidate_id)]);
    }

    public function edit($id)
    {
        $this->needManage();
        $this->offer = JobOffer::with('application.candidate', 'application.position')->findOrFail($id);

        if ($this->offer->status !== 'draft') {
            return Reply::error('Only a draft offer can be edited. Withdraw it and make a new one to change a sent offer.');
        }

        $this->application = $this->offer->application;
        $this->lookups();

        return $this->panel('recruitment.offers.form', 'Edit Offer — ' . $this->application->candidate->name);
    }

    public function update(Request $request, $id, Pipeline $pipeline)
    {
        $this->needManage();
        $offer = JobOffer::with('application')->findOrFail($id);

        if ($offer->status !== 'draft') {
            return Reply::error('Only a draft offer can be edited.');
        }

        $offer->update($this->validated($request, $offer));
        $pipeline->log($offer->application->candidate_id, $offer->job_application_id, 'offer', 'Offer edited: ' . $this->summary($offer));

        return Reply::successWithData('Offer updated.', ['redirectUrl' => route('candidates.show', $offer->application->candidate_id)]);
    }

    public function respondForm(Request $request, $id)
    {
        $this->needManage();
        $this->offer = JobOffer::with('application.candidate', 'designation')->findOrFail($id);
        $this->choices = self::NEXT[$this->offer->status] ?? [];

        if (!$this->choices) {
            return Reply::error('This offer is ' . strtolower(JobOffer::STATUSES[$this->offer->status][0]) . '; nothing more can be done with it.');
        }

        $this->preset = in_array($request->to, $this->choices, true) ? $request->to : $this->choices[0];

        return $this->panel('recruitment.offers.respond', 'Offer — ' . $this->offer->application->candidate->name);
    }

    public function respond(Request $request, $id, Pipeline $pipeline, CandidateIntake $intake)
    {
        $this->needManage();
        $offer = JobOffer::with('application.candidate', 'application.position')->findOrFail($id);
        $allowed = self::NEXT[$offer->status] ?? [];

        $request->validate([
            'status' => ['required', Rule::in($allowed)],
            'response_note' => 'nullable|string|max:5000|required_if:status,declined|required_if:status,withdrawn',
            'signed_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ], [
            'status.in' => 'That cannot be done with this offer now.',
            'response_note.required_if' => 'Give the reason (it is kept on the candidate\'s record).',
            'signed_file.mimes' => 'The signed copy must be a PDF, JPG or PNG.',
        ]);

        $to = $request->status;

        if ($to === 'accepted' && !$request->hasFile('signed_file') && trim((string) $request->response_note) === '') {
            return Reply::error('Keep the proof of acceptance: upload the signed offer, or note how the candidate accepted (e.g. "Accepted by email on 3 Nov").');
        }

        $application = $offer->application;

        try {
            DB::transaction(function () use ($offer, $to, $request, $application, $pipeline, $intake) {
                if ($to === 'accepted' && $request->hasFile('signed_file')) {
                    $file = $intake->storeUploaded($application->candidate, $request->file('signed_file'), 'signed_offer', 'Signed offer ' . ($offer->reference_no ?: ''));
                    $offer->signed_file_id = $file->id;
                }

                $offer->status = $to;
                $offer->response_note = $request->response_note ?: $offer->response_note;

                if ($to === 'sent') {
                    $offer->sent_at = now();
                } elseif (in_array($to, ['accepted', 'declined'], true)) {
                    $offer->responded_at = now();
                }

                $offer->save();

                $pipeline->log($application->candidate_id, $application->id, 'offer', 'Offer ' . strtolower(JobOffer::STATUSES[$to][0])
                    . ($offer->reference_no ? ' (' . $offer->reference_no . ')' : '')
                    . ($request->response_note ? ' — ' . $request->response_note : '')
                    . ($to === 'accepted' && $offer->signed_file_id ? ' · signed copy kept' : ''));

                match ($to) {
                    'sent' => $pipeline->move($application, 'offered', null, null, false),
                    'accepted' => $pipeline->move($application, 'offer_accepted', null, null, false),
                    'declined' => $pipeline->move($application, 'withdrawn', 'Offer declined', $request->response_note, false),
                    // A withdrawn offer puts the candidate back to Selected, to make another or close.
                    'withdrawn' => $application->stage !== 'joined' ? $pipeline->move($application, 'selected', null, 'Offer withdrawn', false) : null,
                };
            });
        } catch (RuntimeException $e) {
            return Reply::error($e->getMessage());
        }

        $message = ['sent' => 'Offer marked as sent.', 'accepted' => 'Offer accepted. The candidate can now be converted to an employee.',
            'declined' => 'Offer declined; the application is closed.', 'withdrawn' => 'Offer withdrawn.'][$to];

        return Reply::successWithData($message, ['redirectUrl' => route('candidates.show', $application->candidate_id)]);
    }

    /** The printable letter: the company's offer letter, filled from this offer. */
    public function letter($id)
    {
        $this->needView();
        $offer = JobOffer::findOrFail($id);

        return redirect()->route('offer-letter.index', ['offer_id' => $offer->id]);
    }

    private function lookups(): void
    {
        $this->designations = Designation::orderBy('name')->get();
        $this->departments = Team::orderBy('team_name')->get();
        $this->branches = Branch::orderBy('branch_name')->get();
    }

    private function validated(Request $request, ?JobOffer $offer = null): array
    {
        $request->validate([
            'reference_no' => 'nullable|string|max:50',
            'designation_id' => 'required|integer',
            'department_id' => 'nullable|integer',
            'branch_id' => 'nullable|integer',
            'annual_ctc' => 'required|numeric|min:1',
            'notes' => 'nullable|string|max:5000',
        ], ['designation_id.required' => 'Choose the designation offered.', 'annual_ctc.required' => 'Enter the annual CTC offered.']);

        $data = [
            'reference_no' => $request->reference_no ?: null,
            'designation_id' => (int) $request->designation_id,
            'department_id' => $request->department_id ?: null,
            'branch_id' => $request->branch_id ?: null,
            'annual_ctc' => (float) $request->annual_ctc,
            'offer_date' => $this->date($request->offer_date, 'offer date'),
            'joining_date' => $this->date($request->joining_date, 'joining date'),
            'valid_until' => $this->date($request->valid_until, 'valid until date', false),
            'notes' => $request->notes ?: null,
        ];

        if ($data['joining_date'] < $data['offer_date']) {
            $this->fail('The joining date cannot be before the offer date.');
        }

        return $data;
    }

    private function summary(JobOffer $offer): string
    {
        $offer->loadMissing('designation');

        return trim(($offer->designation?->name ?? '') . ' · CTC ' . number_format($offer->annual_ctc) . ' per year · joining '
            . $offer->joining_date->format(company()->date_format), ' ·');
    }

}
