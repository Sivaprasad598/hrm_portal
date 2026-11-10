{{-- One applicant at a glance, opened from a board card: enough to screen them
     (shortlist / reject / move) without leaving the board. --}}
@php
    $c = $candidate;
    $money = fn ($v) => $v ? '₹' . number_format((float) $v / 100000, ((float) $v % 100000) ? 2 : 0) . ' L' : '--';
    $jobs = $c->work_experience ?: [];
    $p = $progress;
@endphp
<style>
    .qv { font-size: 13.5px; }
    .qv-head { display: flex; gap: 14px; align-items: center; padding: 20px 24px; border-bottom: 1px solid var(--epic-line, #e8eef3); }
    .qv-avatar { flex: 0 0 52px; width: 52px; height: 52px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
        background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 14%, #fff); color: var(--epic-blue, #cb6b4f); font-weight: 600; font-size: 18px; }
    .qv-name { font-size: 19px; font-weight: 600; margin: 0; }
    .qv-sub { color: var(--epic-muted, #99a5b5); font-size: 13px; }
    .qv-actions { display: flex; flex-wrap: wrap; gap: 8px; padding: 14px 24px; border-bottom: 1px solid var(--epic-line, #e8eef3);
        background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 4%, #fff); }
    .qv-sec { padding: 16px 24px; border-bottom: 1px solid var(--epic-line, #e8eef3); }
    .qv-sec h6 { font-size: 12px; text-transform: uppercase; letter-spacing: .06em; color: var(--epic-muted, #99a5b5); margin: 0 0 10px; font-weight: 600; }
    .qv-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px 18px; }
    .qv-grid dt { font-size: 12px; color: var(--epic-muted, #99a5b5); font-weight: 500; margin: 0; }
    .qv-grid dd { margin: 2px 0 0; font-weight: 500; word-break: break-word; }
    @media (max-width: 767.98px) { .qv-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .qv-chip { display: inline-block; padding: 2px 10px; margin: 0 6px 6px 0; border-radius: 999px; background: #f1f3f6; font-size: 12.5px; }
    .qv-job { display: flex; justify-content: space-between; gap: 10px; padding: 6px 0; border-bottom: 1px dashed var(--epic-line, #e8eef3); }
    .qv-job:last-child { border-bottom: 0; }
</style>

<div class="qv bg-white">
    <div class="qv-head">
        <span class="qv-avatar">{{ $c->initials() }}</span>
        <div class="flex-grow-1">
            <p class="qv-name">{{ $c->name }}</p>
            <div class="qv-sub">
                {{ $application->positionLabel() }} · {!! $application->stageBadge() !!}
                @if ($application->stage_reason)<span class="ml-1">({{ $application->stage_reason }})</span>@endif
            </div>
            <div class="qv-sub mt-1">
                Applied {{ $application->created_at->format($fmt) }} · {{ $c->sourceLabel() }}{{ $c->source_detail ? ' · ' . $c->source_detail : '' }}
            </div>
        </div>
    </div>

    {{-- What HR does next, right here --}}
    <div class="qv-actions">
        @if ($canManage)
            @if ($application->stage === 'new' && $application->job_position_id)
                <button type="button" class="btn btn-primary btn-sm shortlist-app" data-id="{{ $application->id }}"><i class="fa fa-star mr-1"></i>Shortlist</button>
            @endif
            @if (in_array('rejected', $choices, true))
                <a href="{{ route('job-applications.move-form', [$application->id, 'to' => 'rejected']) }}" class="btn btn-outline-danger btn-sm openRightModal"><i class="fa fa-times mr-1"></i>Reject</a>
            @endif
            @if ($application->stage === 'new' && $application->job_position_id && in_array('on_hold', $choices, true))
                <a href="{{ route('job-applications.move-form', [$application->id, 'to' => 'on_hold']) }}" class="btn btn-outline-secondary btn-sm openRightModal"><i class="fa fa-pause mr-1"></i>On hold</a>
            @endif
            @if ($application->stage !== 'joined')
                <a href="{{ route('job-applications.move-form', $application->id) }}" class="btn btn-outline-secondary btn-sm openRightModal">{{ $application->position ? 'Move to…' : 'Assign a position' }}</a>
            @endif
            @if (in_array($application->stage, ['shortlisted', 'interview', 'on_hold']))
                <a href="{{ route('recruit-interviews.create', ['application' => $application->id]) }}" class="btn btn-outline-secondary btn-sm openRightModal"><i class="fa fa-calendar-plus mr-1"></i>Schedule interview</a>
            @endif
        @endif
        <a href="{{ route('candidates.show', $c->id) }}" class="btn btn-outline-primary btn-sm ml-auto">Open full profile <i class="fa fa-arrow-right ml-1"></i></a>
    </div>

    <div class="qv-sec">
        <h6>Contact</h6>
        <dl class="qv-grid mb-0">
            <div><dt>Email</dt><dd>{!! $c->email ? '<a href="mailto:' . e($c->email) . '">' . e($c->email) . '</a>' : '--' !!}</dd></div>
            <div><dt>Mobile</dt><dd>{!! $c->mobile ? '<a href="tel:' . e($c->mobile) . '">' . e($c->mobile) . '</a>' : '--' !!}</dd></div>
            <div><dt>Location</dt><dd>{{ $c->current_location ?: '--' }}</dd></div>
        </dl>
    </div>

    <div class="qv-sec">
        <h6>Experience &amp; pay</h6>
        <dl class="qv-grid mb-0">
            <div><dt>Experience</dt><dd>{{ $c->experienceText() }}</dd></div>
            <div><dt>Current company</dt><dd>{{ $c->current_company ?: '--' }}</dd></div>
            <div><dt>Designation</dt><dd>{{ $c->current_designation ?: '--' }}</dd></div>
            <div><dt>Qualification</dt><dd>{{ $c->qualification ?: '--' }}</dd></div>
            <div><dt>Current CTC</dt><dd>{{ $money($c->current_ctc) }}</dd></div>
            <div><dt>Expected CTC</dt><dd>{{ $money($c->expected_ctc) }}
                @if ($application->position && $application->position->salary_max && $c->expected_ctc > $application->position->salary_max)
                    <span class="text-danger f-12 d-block">above the position's range</span>
                @endif</dd></div>
            <div><dt>Notice period</dt><dd>{{ $c->notice_period_days !== null ? ($c->notice_period_days ? $c->notice_period_days . ' days' : 'Immediate') : '--' }}</dd></div>
            @if ($application->position)
                <div><dt>Position asks</dt><dd>{{ $application->position->experience_min !== null ? rtrim(rtrim(number_format($application->position->experience_min, 1), '0'), '.') . '–' . rtrim(rtrim(number_format($application->position->experience_max, 1), '0'), '.') . ' yrs' : '--' }}</dd></div>
                <div><dt>Position pays</dt><dd>{{ $application->position->salary_min ? $money($application->position->salary_min) . ' – ' . $money($application->position->salary_max) : '--' }}</dd></div>
            @endif
        </dl>
    </div>

    @if ($c->skills)
        <div class="qv-sec">
            <h6>Skills</h6>
            @foreach ($c->skills as $skill)<span class="qv-chip">{{ $skill }}</span>@endforeach
        </div>
    @endif

    @if ($application->cover_note)
        <div class="qv-sec">
            <h6>Cover note</h6>
            <div class="text-dark-grey" style="white-space: pre-line">{{ $application->cover_note }}</div>
        </div>
    @endif

    @if ($jobs)
        <div class="qv-sec">
            <h6>Work history</h6>
            @foreach ($jobs as $job)
                <div class="qv-job">
                    <span><strong>{{ $job['designation'] ?? '' }}</strong>{{ !empty($job['company_name']) ? ' · ' . $job['company_name'] : '' }}{{ !empty($job['location']) ? ' · ' . $job['location'] : '' }}</span>
                    <span class="text-dark-grey text-nowrap">{{ $job['joining_date'] ?? '' }} – {{ !empty($job['leaving_date']) ? $job['leaving_date'] : 'now' }}</span>
                </div>
            @endforeach
        </div>
    @endif

    @if ($p && in_array($application->stage, ['shortlisted', 'interview', 'selected', 'offered', 'offer_accepted', 'rejected', 'on_hold'], true) && ($p['at'] || $p['scores']))
        <div class="qv-sec">
            <h6>Rounds</h6>
            @if ($p['at'])<div class="mb-1"><strong>Round {{ $p['at'] }} of {{ $p['of'] }}</strong>@if ($p['proposed']) · <span class="text-info">slot suggested, waiting for HR to confirm</span>@endif</div>@endif
            @if ($p['scores'])<div class="text-dark-grey">{{ implode(' · ', $p['scores']) }}</div>@endif
        </div>
    @endif

    <div class="qv-sec">
        <h6>Resume</h6>
        @if ($c->resume)
            <a href="{{ $c->resume->url }}" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="fa fa-file-alt mr-1"></i>{{ $c->resume->filename }}</a>
        @else
            <span class="text-dark-grey">No resume on file.</span>
        @endif
    </div>

    @if ($otherApplications->isNotEmpty())
        <div class="qv-sec">
            <h6>Also applied for</h6>
            @foreach ($otherApplications as $other)
                <div>{{ $other->positionLabel() }} · {!! $other->stageBadge() !!}</div>
            @endforeach
        </div>
    @endif

    @if ($c->notes)
        <div class="qv-sec">
            <h6>Internal notes</h6>
            <div class="text-dark-grey" style="white-space: pre-line">{{ $c->notes }}</div>
        </div>
    @endif
</div>

<script>
    // Shortlist from the panel, wherever it was opened (board or Resumes): the
    // system then suggests round 1's slot. Bound once, by name.
    $('body').off('click.qvShortlist').on('click.qvShortlist', '.qv .shortlist-app', function () {
        $.easyAjax({
            url: '{{ route('job-applications.move', ':id') }}'.replace(':id', $(this).data('id')), type: 'POST', blockUI: true,
            data: {_token: '{{ csrf_token() }}', stage: 'shortlisted'},
            success: function (response) { if (response.status === 'success') { window.location.reload(); } }
        });
    });
</script>
