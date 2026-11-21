{{-- "Referred by" filter (Applicants, Resumes): only the employees who have referred someone. --}}
@php $referrerOptions = \App\Services\Recruit\Referral::options(); @endphp
@if ($referrerOptions->isNotEmpty())
    <div class="{{ $col ?? 'col-md-2 col-6' }} mb-2">
        <label class="f-13 text-dark-grey mb-1">Referred by</label>
        <select name="referred_by" class="form-control select-picker" data-live-search="true">
            <option value="">Anyone</option>
            @foreach ($referrerOptions as $id => $label)
                <option value="{{ $id }}" @selected((int) request('referred_by') === (int) $id)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
@endif
