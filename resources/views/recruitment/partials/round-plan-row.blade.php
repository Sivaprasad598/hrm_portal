@php
    $name = 'rounds[' . $idx . ']';
    $type = $r?->type ?? 'interview';
    $panel = array_map('intval', $r?->interviewer_ids ?? []);
@endphp
<div class="rp-row">
    <input type="hidden" name="{{ $name }}[id]" value="{{ $r?->id }}">
    <div class="d-flex align-items-center mb-2">
        <span class="rp-no mr-2">{{ is_int($idx) ? $idx + 1 : '' }}</span>
        <input type="text" name="{{ $name }}[name]" class="form-control f-14 font-weight-600" maxlength="100"
            value="{{ $r?->name }}" placeholder="Round name, e.g. Aptitude test, Technical interview">
        <span class="rp-tools text-nowrap ml-2">
            <a href="javascript:;" class="rp-up" title="Move up"><i class="fa fa-arrow-up"></i></a>
            <a href="javascript:;" class="rp-down" title="Move down"><i class="fa fa-arrow-down"></i></a>
            <a href="javascript:;" class="rp-remove text-danger" title="Remove"><i class="fa fa-times"></i></a>
        </span>
    </div>
    <div class="row">
        <div class="col-md-3 col-6 mb-2">
            <label>Kind</label>
            <select name="{{ $name }}[type]" class="form-control f-14 rp-type">
                @foreach (\App\Models\RecruitRound::TYPES as $key => $label)
                    <option value="{{ $key }}" @selected($type === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2 col-6 mb-2">
            <label>Mode</label>
            <select name="{{ $name }}[mode]" class="form-control f-14">
                @foreach (\App\Models\RecruitInterview::MODES as $key => [$label])
                    <option value="{{ $key }}" @selected(($r?->mode ?? 'in_person') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2 col-6 mb-2">
            <label>Duration</label>
            <select name="{{ $name }}[duration]" class="form-control f-14">
                @foreach (\App\Models\RecruitInterview::DURATIONS as $minutes)
                    <option value="{{ $minutes }}" @selected((int) ($r?->duration ?? 30) === $minutes)>{{ $minutes }} min</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2 col-6 mb-2 rp-test-only {{ $type === 'test' ? '' : 'd-none' }}">
            <label>Out of (marks)</label>
            <input type="number" step="0.5" min="1" name="{{ $name }}[max_marks]" class="form-control f-14" value="{{ $r?->max_marks !== null ? \App\Models\RecruitRound::num($r->max_marks) : '' }}" placeholder="100">
        </div>
        <div class="col-md-3 col-6 mb-2">
            <label class="rp-cut-label">{{ $type === 'test' ? 'Cut-off (marks)' : 'Pass mark (of 5)' }}</label>
            <input type="number" step="0.1" min="0" name="{{ $name }}[cut_off]" class="form-control f-14" value="{{ $r?->cut_off !== null ? \App\Models\RecruitRound::num($r->cut_off) : '' }}" placeholder="empty: HR decides">
        </div>
        <div class="col-md-6 mb-2">
            <label class="rp-panel-label">{{ $type === 'test' ? 'Conducted / marked by' : 'Interview panel' }}</label>
            <select name="{{ $name }}[interviewer_ids][]" class="form-control rp-panel" multiple data-live-search="true" data-size="8"
                data-none-selected-text="Choose employees (HR assigns at confirm if empty)" data-container="body">
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}" @selected(in_array((int) $employee->id, $panel, true))>{{ $employee->name }}{{ $employee->employee_id ? ' (' . $employee->employee_id . ')' : '' }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6 mb-2 rp-interview-only {{ $type === 'test' ? 'd-none' : '' }}">
            <label>Scorecard questions (one per line; empty: the usual ones)</label>
            <textarea name="{{ $name }}[questions]" rows="2" class="form-control f-13" placeholder="Technical knowledge&#10;Communication">{{ $r?->questions }}</textarea>
        </div>
    </div>
</div>
