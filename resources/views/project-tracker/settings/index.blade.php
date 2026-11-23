@extends('layouts.app')

@section('content')
@include('recruitment.partials.styles')
<div class="w-100 d-flex">

    @include('sections.setting-sidebar')

    <x-setting-card>
        <x-slot name="header">
            <div class="s-b-n-header d-flex justify-content-between align-items-center border-bottom-grey">
                <h2 class="mb-0 p-20 f-21 font-weight-normal">Project Tracker</h2>
                <a href="{{ route('project-types.create') }}" class="btn btn-primary rounded f-14 mr-3 openRightModal"><i class="fa fa-plus mr-1"></i>Add Project Type</a>
            </div>
        </x-slot>

        <div class="col-lg-12 col-md-12 w-100 p-4 pt-tracker" data-swap-links>

            {{-- Working hours: what a working day counts for in capacity --}}
            <div class="pt-card mb-4">
                <form id="hours-form" class="pt-hours" onsubmit="return false">
                    @csrf
                    <div class="pt-hours-text">
                        <h5><i class="fa fa-clock mr-1"></i>Working hours a day</h5>
                        <p>Each employee's capacity is their shift days (less leave and holidays) times this. 8 is the full day; set 7 to leave room for meetings and admin.</p>
                    </div>
                    <div class="pt-hours-field">
                        <div class="pt-seg" role="radiogroup" aria-label="Working hours a day">
                            @foreach ([7, 7.5, 8] as $h)
                                <label class="{{ (float) $setting->workday_hours === (float) $h ? 'active' : '' }}"><input type="radio" name="workday_hours" value="{{ $h }}" @checked((float) $setting->workday_hours === (float) $h)>{{ $h }} h</label>
                            @endforeach
                        </div>
                        <span class="text-dark-grey f-13 ml-2">or</span>
                        <input type="number" step="0.5" min="1" max="12" class="form-control pt-hours-other" id="hours-other" placeholder="other"
                               value="{{ in_array((float) $setting->workday_hours, [7.0, 7.5, 8.0], true) ? '' : (float) $setting->workday_hours }}" aria-label="Other working hours">
                    </div>
                </form>
            </div>

            {{-- Project types: one per production Division --}}
            <div class="pt-section-head">
                <h5>Project types <span class="pt-count">{{ $types->count() }}</span></h5>
                <p>What a project's work is counted in, the steps it goes through, and how much each designation does in a day. A project takes its type's steps and grace days.</p>
            </div>

            <div class="row">
                @forelse ($types as $type)
                    <div class="col-xl-6 mb-3">
                        <div class="pt-type {{ $type->active ? '' : 'is-off' }}" style="--tc: {{ $type->color ?: '#cb6b4f' }}">
                            <div class="pt-type-head">
                                <span class="pt-type-dot"></span>
                                <div style="min-width: 0">
                                    <div class="pt-type-name">{{ $type->name }} @unless ($type->active)<span class="pt-pill">Not in use</span>@endunless</div>
                                    <div class="pt-type-sub">{{ $type->division?->team_name ?? 'Any Division' }} · counted in <b>{{ $type->unitText() }}</b> · {{ $type->grace_days }} grace day{{ $type->grace_days == 1 ? '' : 's' }}</div>
                                </div>
                                <a href="{{ route('project-types.edit', $type->id) }}" class="btn btn-sm btn-outline-secondary openRightModal ml-auto"><i class="fa fa-edit mr-1"></i>Edit</a>
                            </div>

                            <div class="pt-flow">
                                @foreach ($type->stages as $i => $stage)
                                    <span class="pt-step" style="--sc: {{ $stage->colour($i) }}">{{ $stage->name }}</span>
                                    @unless ($loop->last)<i class="fa fa-chevron-right pt-arrow"></i>@endunless
                                @endforeach
                            </div>

                            <div class="pt-type-foot">
                                <span><i class="fa fa-tachometer-alt mr-1"></i>
                                    @if ($type->rates->isEmpty())
                                        <span class="text-warning">No daily rates yet</span> — add them so estimates can be worked out
                                    @else
                                        {{ $type->rates->take(3)->map(fn ($r) => ($r->designation?->name ?? '?') . ' ' . rtrim(rtrim(number_format($r->units_per_day, 2), '0'), '.') . ' ' . $type->unitText() . '/day')->implode(' · ') }}{{ $type->rates->count() > 3 ? ' · +' . ($type->rates->count() - 3) . ' more' : '' }}
                                    @endif
                                </span>
                                <span class="ml-auto text-nowrap">{{ $type->projects_count }} project{{ $type->projects_count == 1 ? '' : 's' }}</span>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12"><x-cards.no-record icon="layer-group" message="No project types yet. Add one for each Division that runs projects." /></div>
                @endforelse
            </div>
        </div>
    </x-setting-card>
</div>

<style>
    .pt-tracker .pt-card { background: #fff; border: 1px solid #eaecf0; border-radius: 12px; padding: 18px 20px; }
    .pt-hours { display: flex; flex-wrap: wrap; align-items: center; gap: 16px; margin: 0; }
    .pt-hours-text { flex: 1; min-width: 260px; }
    .pt-hours-text h5, .pt-section-head h5 { font-size: 15px; font-weight: 600; margin: 0 0 4px; color: #1d2939; }
    .pt-hours-text p, .pt-section-head p { font-size: 13px; color: #667085; margin: 0; }
    .pt-hours-field { display: flex; align-items: center; }
    .pt-seg { display: inline-flex; padding: 3px; background: #f2f4f7; border-radius: 9px; }
    .pt-seg label { margin: 0; padding: 7px 16px; border-radius: 7px; font-size: 14px; font-weight: 500; color: #475467; cursor: pointer; }
    .pt-seg label input { display: none; }
    .pt-seg label.active { background: #fff; color: var(--epic-blue, #cb6b4f); box-shadow: 0 1px 3px rgba(16, 24, 40, .12); }
    .pt-hours-other { width: 90px; height: 38px; margin-left: 8px; }
    .pt-section-head { margin: 0 0 14px; }
    .pt-count { font-size: 12px; background: #f2f4f7; color: #475467; border-radius: 10px; padding: 1px 8px; margin-left: 4px; font-weight: 600; }
    .pt-type { height: 100%; background: #fff; border: 1px solid #eaecf0; border-top: 3px solid var(--tc); border-radius: 12px; padding: 16px 18px; display: flex; flex-direction: column; gap: 12px; }
    .pt-type.is-off { opacity: .6; }
    .pt-type-head { display: flex; align-items: flex-start; gap: 10px; }
    .pt-type-dot { flex: 0 0 12px; width: 12px; height: 12px; margin-top: 5px; border-radius: 50%; background: var(--tc); }
    .pt-type-name { font-size: 15.5px; font-weight: 600; color: #101828; }
    .pt-type-sub { font-size: 12.5px; color: #667085; margin-top: 2px; }
    .pt-pill { font-size: 11px; font-weight: 500; background: #f2f4f7; color: #667085; border-radius: 999px; padding: 1px 8px; margin-left: 4px; }
    .pt-flow { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
    .pt-step { font-size: 12.5px; font-weight: 500; padding: 3px 10px; border-radius: 999px; color: var(--sc); background: color-mix(in srgb, var(--sc) 12%, #fff);
        border: 1px solid color-mix(in srgb, var(--sc) 30%, #fff); }
    .pt-arrow { font-size: 9px; color: #d0d5dd; }
    .pt-type-foot { display: flex; gap: 10px; align-items: center; margin-top: auto; padding-top: 10px; border-top: 1px dashed #eaecf0; font-size: 12.5px; color: #475467; }
    .pt-type .btn-sm { height: 32px; display: inline-flex; align-items: center; border-radius: 8px !important; padding: 0 12px !important; white-space: nowrap; }
</style>
@endsection

@push('scripts')
<script>
    // Working hours: saved the moment one is chosen (or typed)
    (function () {
        var save = function (value) {
            $.easyAjax({
                url: '{{ route('project-tracker-settings.hours') }}', type: 'POST', container: '#hours-form',
                data: {_token: '{{ csrf_token() }}', workday_hours: value}
            });
        };
        $('#hours-form').on('change', 'input[name=workday_hours]', function () {
            $('.pt-seg label').removeClass('active');
            $(this).closest('label').addClass('active');
            $('#hours-other').val('');
            save(this.value);
        });
        var t;
        $('#hours-other').on('input', function () {
            var v = this.value;
            clearTimeout(t);
            if (!v) { return; }
            t = setTimeout(function () { $('.pt-seg label').removeClass('active').find('input').prop('checked', false); save(v); }, 700);
        });
    })();
</script>
@endpush
