@extends('layouts.app')

@push('styles')
    <style>
        /* Payroll dashboard in the Epic HR look: white widgets, the tone as an icon chip */
        .pd-strip { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 10px 14px; background: #fff; border-radius: 8px; box-shadow: 0 1px 2px rgba(56, 65, 74, .15); }
        .pd-chip { font-size: 12px; padding: 4px 12px; border-radius: 999px; background: #f6f6f7; border: 1px solid var(--epic-line, #e8e9e9); color: #475467; }
        .pd-chip--dark { background: var(--epic-dark, #111); border-color: var(--epic-dark, #111); color: #fff; }
        .pd-chip--warn { background: #fffaeb; border-color: #fedf89; color: #b54708; }
        .pd-section-title { font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--epic-text, #212529); margin: 4px 0 12px; display: flex; align-items: baseline; gap: 8px; }
        .pd-section-title small { font-weight: 400; text-transform: none; letter-spacing: 0; color: var(--epic-muted, #878a99); font-size: 12px; }
        .pd-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 16px; }
        .pd-grid--small { grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 12px; }

        .pd-card { --tone: #b8892e; position: relative; display: flex; flex-direction: column; background: #fff; border-radius: 8px; padding: 16px 18px 12px; cursor: pointer;
                   box-shadow: 0 1px 2px rgba(56, 65, 74, .15); transition: box-shadow .15s, transform .15s; color: var(--epic-text, #212529); }
        .pd-card:hover, .pd-card:focus { box-shadow: 0 8px 24px rgba(56, 65, 74, .16); transform: translateY(-1px); outline: 0; }
        .pd-card__top { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; }
        .pd-card__label { font-size: 11.5px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--epic-muted, #878a99); }
        .pd-card__icon { width: 38px; height: 38px; min-width: 38px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; font-size: 15px;
                         background: color-mix(in srgb, var(--tone) 13%, #fff); color: var(--tone); }
        .pd-card__value { font-size: 23px; font-weight: 700; margin-top: 2px; white-space: nowrap; letter-spacing: -.01em; }
        .pd-card__sub { font-size: 12px; color: var(--epic-muted, #878a99); margin-top: 1px; }
        .pd-card__lines { margin-top: 12px; padding-top: 8px; border-top: 1px solid #f0f1f3; flex: 1; }
        .pd-card__line { display: flex; justify-content: space-between; gap: 8px; font-size: 12.5px; line-height: 1.9; }
        .pd-card__line span:first-child { color: #667085; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .pd-card__line span:last-child { font-weight: 600; white-space: nowrap; color: var(--epic-text, #212529); }
        .pd-card__more { font-size: 12px; font-weight: 600; color: var(--epic-blue, #b8892e); margin-top: 8px; display: inline-flex; align-items: center; gap: 4px; }
        .pd-card__more i, .pd-card__more svg { font-size: 10px; }
        .pd-blue { --tone: #2e90fa; } .pd-green { --tone: #12b76a; } .pd-teal { --tone: #0e9384; } .pd-red { --tone: #f04438; }
        .pd-orange { --tone: #ef6820; } .pd-indigo { --tone: #6172f3; } .pd-dark { --tone: #344054; } .pd-purple { --tone: #7a5af8; } .pd-amber { --tone: var(--epic-blue, #b8892e); }
        .pd-card.pd-blue .pd-card__value, .pd-card.pd-dark .pd-card__value { color: var(--epic-text, #212529); }

        .pd-mini { --tone: #12b76a; display: flex; align-items: center; gap: 12px; border-radius: 8px; padding: 12px 14px; cursor: pointer; background: #fff;
                   box-shadow: 0 1px 2px rgba(56, 65, 74, .15); transition: box-shadow .15s; }
        .pd-mini:hover, .pd-mini:focus { box-shadow: 0 6px 18px rgba(56, 65, 74, .14); outline: 0; }
        .pd-mini.pd-red { --tone: #f04438; }
        .pd-mini.is-nil { opacity: .55; }
        .pd-mini__sign { width: 30px; height: 30px; min-width: 30px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: 15px;
                         background: color-mix(in srgb, var(--tone) 13%, #fff); color: var(--tone); }
        .pd-mini__body { min-width: 0; }
        .pd-mini__label { font-size: 12.5px; font-weight: 600; color: #344054; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .pd-mini__value { font-size: 17px; font-weight: 700; white-space: nowrap; color: var(--tone); }
        .pd-mini__sub { font-size: 11.5px; color: var(--epic-muted, #878a99); }

        /* Statutory reports: one tile per return */
        .pd-returns { background: #fff; border-radius: 8px; box-shadow: 0 1px 2px rgba(56, 65, 74, .15); }
        .pd-returns__head { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; padding: 14px 18px; border-bottom: 1px solid var(--epic-line, #e8e9e9); }
        .pd-returns__head h6 { margin: 0; font-size: 15px; font-weight: 600; }
        .pd-returns__head p { margin: 0; font-size: 12px; color: var(--epic-muted, #878a99); }
        .pd-returns__head select { width: auto; min-width: 170px; height: 38px; font-size: 13px; }
        .pd-returns__grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 12px; padding: 16px 18px; }
        .pd-return { --tone: #7a5af8; display: flex; flex-direction: column; gap: 6px; padding: 14px; border: 1px solid var(--epic-line, #e8e9e9); border-radius: 8px; transition: border-color .15s, box-shadow .15s; }
        .pd-return:hover { border-color: color-mix(in srgb, var(--tone) 40%, #fff); box-shadow: 0 4px 14px rgba(16, 24, 40, .06); }
        .pd-return__top { display: flex; align-items: center; gap: 10px; }
        .pd-return__icon { width: 34px; height: 34px; min-width: 34px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; font-size: 15px;
                           background: color-mix(in srgb, var(--tone) 13%, #fff); color: var(--tone); }
        .pd-return__name { font-size: 13.5px; font-weight: 600; color: var(--epic-text, #212529); line-height: 1.25; }
        .pd-return__fmt { margin-left: auto; font-size: 10.5px; font-weight: 700; letter-spacing: .05em; padding: 2px 7px; border-radius: 4px; background: #f2f4f7; color: #475467; }
        .pd-return__desc { font-size: 12px; color: #667085; line-height: 1.45; flex: 1; }
        .pd-return .pd-download { align-self: flex-start; font-size: 12.5px; font-weight: 600; padding: 5px 12px; border-radius: 6px; border: 1px solid color-mix(in srgb, var(--tone) 35%, #fff);
                                  color: var(--tone); background: #fff; }
        .pd-return .pd-download:hover { background: var(--tone); color: #fff; }
        .pd-returns__foot { padding: 0 18px 14px; font-size: 12px; color: var(--epic-muted, #878a99); }
        .pd-returns__head .pd-download { margin-left: auto; }

        /* The two tables */
        .pd-panel { background: #fff; border-radius: 8px; box-shadow: 0 1px 2px rgba(56, 65, 74, .15); height: 100%; display: flex; flex-direction: column; overflow: hidden; }
        .pd-panel__head { padding: 14px 18px; border-bottom: 1px solid var(--epic-line, #e8e9e9); }
        .pd-panel__head h6 { margin: 0; font-size: 15px; font-weight: 600; display: block; }
        .pd-panel__head p { margin: 2px 0 0; font-size: 12px; color: var(--epic-muted, #878a99); display: block; }
        .dash-table { margin: 0; }
        .dash-table th, .dash-table td { white-space: nowrap; vertical-align: middle !important; padding: 10px 14px !important; }
        .dash-table thead th { position: sticky; top: 0; background: #fafafa; z-index: 2; font-size: 11px !important; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: var(--epic-muted, #878a99); border-bottom: 1px solid var(--epic-line, #e8e9e9) !important; }
        .dash-table tbody td { border-top: 1px solid #f3f3f4 !important; font-size: 13px; }
        .dash-table .num { text-align: right; font-variant-numeric: tabular-nums; }
        .pd-who b { display: block; font-weight: 600; color: var(--epic-text, #212529); }
        .pd-who small { display: block; font-size: 11.5px; color: var(--epic-muted, #878a99); }
        .pd-extra { display: block; width: max-content; margin: 2px 0 2px auto; font-size: 11.5px; font-weight: 600; padding: 2px 8px; border-radius: 999px; }
        .pd-extra.credit { background: #ecfdf3; color: #067647; }
        .pd-extra.debit { background: #fef3f2; color: #b42318; }
        .pd-open { width: 30px; height: 30px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--epic-line, #e8e9e9); color: #475467; }
        .pd-open:hover { border-color: var(--epic-blue, #b8892e); color: var(--epic-blue, #b8892e); text-decoration: none; }

        /* Polish: a tone line along the top, the breakdown on a soft panel, the link as a footer */
        .pd-card { padding-top: 18px; overflow: hidden; }
        .pd-card::before { content: ''; position: absolute; left: 0; right: 0; top: 0; height: 3px; background: linear-gradient(90deg, var(--tone), color-mix(in srgb, var(--tone) 35%, #fff)); }
        .pd-card__value { font-size: 24px; }
        .pd-card__lines { margin: 14px -18px 0; padding: 8px 18px; border-top: 1px solid #f0f1f3; background: #fcfcfd; }
        .pd-card__line + .pd-card__line { border-top: 1px dashed #eceef1; }
        .pd-card__more { margin: auto -18px -12px; padding: 10px 18px; border-top: 1px solid #f0f1f3; justify-content: space-between; display: flex; }
        .pd-card:hover .pd-card__more { background: color-mix(in srgb, var(--tone) 6%, #fff); }
        .pd-section-title::before { content: ''; width: 4px; height: 14px; border-radius: 2px; background: var(--epic-blue, #b8892e); align-self: center; }

        /* The list behind a card: wide, the table with room for its scrollbar */
        #pd-modal .modal-dialog { max-width: min(1500px, 96vw); }
        #pd-modal .table-responsive { max-height: 68vh !important; padding-bottom: 14px; }
        #pd-modal .pd-table { min-width: 100%; }
        #pd-modal .pd-table td, #pd-modal .pd-table th { padding: 10px 14px; vertical-align: middle; }
        #pd-modal .pd-table tfoot td { bottom: 0; box-shadow: 0 -1px 0 var(--epic-line, #e8e9e9); }

        .pd-modal-head { background: var(--epic-dark, #111); color: #fff; }
        .pd-modal-head p { color: rgba(255, 255, 255, .7); }
        .pd-table th, .pd-table td { white-space: nowrap; }
        .pd-table thead th { position: sticky; top: 0; background: #fafafa; color: var(--epic-muted, #878a99); z-index: 2; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; }
        .pd-table tfoot td { background: #faf5ea; font-weight: 700; position: sticky; bottom: 0; }
        .credit { color: #067647; }
        .debit { color: #b42318; }
    </style>
@endpush

@section('content')
    @php
        $money = fn ($v) => '₹' . number_format((float) $v, 2);
    @endphp

    <div class="content-wrapper">

        {{-- Which stretch of time --}}
        <div class="card bg-white border-0 b-shadow-4 mb-3">
            <div class="card-body p-3">
                <form method="GET" action="{{ route('payroll-dashboard.index') }}">
                    <div class="row align-items-end">
                        <div class="col-md-3 mb-2">
                            <label class="f-12 text-dark-grey">Period</label>
                            <select name="preset" class="form-control f-14" onchange="this.form.from.value=''; this.form.to.value=''; this.form.submit();">
                                @foreach ($presets as $key => $label)
                                    <option value="{{ $key }}" @selected($preset === $key)>{{ $label }}</option>
                                @endforeach
                                <option value="custom" @selected($preset === 'custom')>Chosen dates</option>
                            </select>
                        </div>
                        <div class="col-md-2 mb-2">
                            <label class="f-12 text-dark-grey">From</label>
                            <input type="date" name="from" class="form-control f-14" value="{{ $from->toDateString() }}">
                        </div>
                        <div class="col-md-2 mb-2">
                            <label class="f-12 text-dark-grey">To</label>
                            <input type="date" name="to" class="form-control f-14" value="{{ $to->toDateString() }}">
                        </div>
                        <div class="col-md-2 mb-2">
                            <button type="submit" class="btn btn-primary f-14 w-100">@lang('app.apply')</button>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="{{ route('payroll-register.index') }}" class="btn btn-outline-secondary f-14 w-100">
                                <i class="fa fa-book mr-1"></i> Salary Statement
                            </a>
                        </div>
                        <div class="col-12">
                            <label class="f-12 text-lightest mb-0 mr-3">
                                <input type="checkbox" name="include_drafts" value="1" @checked($includeDrafts)
                                       onchange="this.form.submit()">
                                Include payslips still in draft
                            </label>
                            <span class="f-12 text-lightest">
                                A payslip counts here when its pay period <strong>started</strong> between these dates,
                                so no payslip is ever counted twice.
                            </span>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        @if ($rows === [])
            <div class="card bg-white border-0 b-shadow-4">
                <div class="card-body text-center p-5">
                    <h6 class="f-16 mb-2">No payslips in this stretch</h6>
                    <p class="f-13 text-lightest mb-3">
                        {{ $from->format('d M Y') }} to {{ $to->format('d M Y') }}{{ $includeDrafts ? '' : ' — drafts are left out; tick "Include payslips still in draft" to see them' }}.
                    </p>
                    <a href="{{ route('payroll-run.index') }}" class="btn btn-sm btn-primary">Run payroll</a>
                </div>
            </div>
        @else
            <div class="pd-strip mb-3">
                <span class="pd-chip pd-chip--dark"><i class="fa fa-calendar-alt mr-1"></i>{{ implode(' · ', $periods) }}</span>
                <span class="pd-chip">{{ $totals['payslips'] }} payslip(s) · {{ $totals['employees'] }} employee(s)</span>
                <span class="pd-chip">{{ $totals['paid_count'] }} paid</span>
                @if ($includeDrafts)
                    <span class="pd-chip pd-chip--warn">drafts included</span>
                @endif
                <span class="ml-auto f-12 text-lightest">Click any card for the full list</span>
            </div>

            @foreach ($sections as $section)
                <div class="pd-section-title">{{ $section['title'] }} <small>{{ $section['note'] }}</small></div>
                <div class="pd-grid mb-4">
                    @foreach ($section['cards'] as $card)
                        @include('payroll-dashboard.card', ['card' => $card])
                    @endforeach
                </div>
            @endforeach

            {{-- Every pay element in the catalogue: credits and debits apart --}}
            @foreach (['earning' => ['Pay Elements — Credits', 'added to pay', 'green', '+'], 'deduction' => ['Pay Elements — Debits', 'taken from pay', 'red', '−']] as $kind => [$title, $note, $tone, $sign])
                <div class="pd-section-title">{{ $title }} <small>{{ $note }}</small></div>
                <div class="pd-grid pd-grid--small mb-4">
                    @foreach ($elementCards[$kind] as $element)
                        <div class="pd-mini pd-{{ $tone }} {{ abs($element['total']) < 0.005 ? 'is-nil' : '' }}" role="button" tabindex="0"
                             data-card="{{ $element['key'] }}" data-title="{{ $element['name'] }}">
                            <span class="pd-mini__sign">{{ $sign }}</span>
                            <div class="pd-mini__body">
                                <div class="pd-mini__label" title="{{ $element['name'] }}">{{ $element['name'] }}</div>
                                <div class="pd-mini__value">{{ $money($element['total']) }}</div>
                                <div class="pd-mini__sub">{{ $element['people'] }} employee(s)</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach

            {{-- Statutory files for one pay period: one tile per return --}}
            @if ($canDownload)
                <div class="pd-returns mb-4">
                    <div class="pd-returns__head">
                        <div>
                            <h6>Statutory reports</h6>
                            <p>The returns for one pay period, built from its {{ $includeDrafts ? 'payslips, drafts included' : 'approved and paid payslips' }}.</p>
                        </div>
                        <select id="pd-return-period" class="form-control">
                            @foreach ($returnPeriods as $p)
                                <option value="{{ $p['month'] }}|{{ $p['year'] }}">{{ $p['label'] }}</option>
                            @endforeach
                        </select>
                        <a href="javascript:;" class="btn btn-primary btn-sm f-13 pd-download" data-what="bundle"><i class="fa fa-file-archive mr-1"></i>Download all (ZIP)</a>
                    </div>
                    <div class="pd-returns__grid">
                        @foreach ([
                            ['pf', 'fa-file-alt', 'PF — ECR upload', 'TXT', '#7a5af8', 'EPFO ECR 2.0 text file for the unified portal: UAN, wages, EE / ER shares, NCP days.'],
                            ['sheet:pf', 'fa-shield-alt', 'PF register', 'XLSX', '#7a5af8', 'The same lines as a sheet with totals, to check before uploading the ECR.'],
                            ['sheet:esi', 'fa-heartbeat', 'ESI return', 'XLSX', '#0e9384', 'ESIC monthly upload layout (IP number, days, wages, reason code) and the contributions.'],
                            ['sheet:pt', 'fa-landmark', 'Professional tax', 'XLSX', '#b8892e', 'Each employee\'s PT for the month with the slab totals, for the state return.'],
                            ['sheet:tds', 'fa-file-invoice', 'TDS — salary (24Q)', 'XLSX', '#f04438', 'Annexure II details: code, PAN, name, section 192, paid, TDS and date of credit.'],
                            ['sheet:all', 'fa-layer-group', 'All returns workbook', 'XLSX', '#344054', 'PF, ESI, PT and TDS as sheets of one workbook.'],
                        ] as [$what, $icon, $name, $fmt, $tone, $desc])
                            <div class="pd-return" style="--tone: {{ $tone }}">
                                <div class="pd-return__top">
                                    <span class="pd-return__icon"><i class="fa {{ $icon }}"></i></span>
                                    <span class="pd-return__name">{{ $name }}</span>
                                    <span class="pd-return__fmt">{{ $fmt }}</span>
                                </div>
                                <div class="pd-return__desc">{{ $desc }}</div>
                                <a href="javascript:;" class="pd-download" data-what="{{ $what }}"><i class="fa fa-download mr-1"></i>Download</a>
                            </div>
                        @endforeach
                    </div>
                    <div class="pd-returns__foot"><i class="fa fa-info-circle mr-1"></i>Missing UAN, ESI numbers or PAN are listed under "Check before filing" at the foot of each sheet; members without a UAN are kept out of the ECR file.</div>
                </div>
            @endif

            <div class="row">
                {{-- Where the cost sat --}}
                <div class="col-12 mb-3">
                    <div class="pd-panel">
                        <div class="pd-panel__head">
                            <h6>By department</h6>
                            <p>Largest cost first.</p>
                        </div>
                        <div class="table-responsive">
                            <table class="table mb-0 dash-table">
                                <thead>
                                    <tr>
                                        <th>Department</th>
                                        <th class="num">Payslips</th>
                                        <th class="num">Paid</th>
                                        <th class="num">Cost</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($departments as $group)
                                        <tr>
                                            <td>{{ $group['department'] }}</td>
                                            <td class="num">{{ $group['people'] }}</td>
                                            <td class="num">{{ $money($group['paid']) }}</td>
                                            <td class="num font-weight-bold">{{ $money($group['cost']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Every individual payout in the stretch --}}
                <div class="col-12 mb-3">
                    <div class="pd-panel">
                        <div class="pd-panel__head">
                            <h6>Individual payouts</h6>
                            <p>{{ count($rows) }} payslip(s) · highest paid first</p>
                        </div>
                        <div class="table-responsive" style="max-height: 52vh">
                            <table class="table table-hover mb-0 dash-table">
                                <thead>
                                    <tr>
                                        <th>@lang('app.employee')</th>
                                        <th>@lang('app.month')</th>
                                        <th class="num">Gross</th>
                                        <th class="num">Deductions</th>
                                        <th class="num">Extras</th>
                                        <th class="num">Paid</th>
                                        <th class="num"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach (collect($rows)->sortByDesc('paid') as $row)
                                        <tr>
                                            <td class="pd-who">
                                                <b>{{ $row['emp_name'] }}</b>
                                                <small>{{ $row['emp_id'] }}{{ $row['department'] ? ' · ' . $row['department'] : '' }}</small>
                                            </td>
                                            <td>{{ $row['period'] }}</td>
                                            <td class="num">{{ $money($row['gross']) }}</td>
                                            <td class="num">{{ $money($row['total_deductions']) }}</td>
                                            <td class="num">
                                                @if ($row['extra_earnings'] > 0)
                                                    <span class="pd-extra credit">+{{ $money($row['extra_earnings']) }}</span>
                                                @endif
                                                @if ($row['extra_deductions'] > 0)
                                                    <span class="pd-extra debit">−{{ $money($row['extra_deductions']) }}</span>
                                                @endif
                                                @if ($row['extra_earnings'] <= 0 && $row['extra_deductions'] <= 0)
                                                    <span class="text-lightest">—</span>
                                                @endif
                                            </td>
                                            <td class="num font-weight-bold">{{ $money($row['paid']) }}</td>
                                            <td class="num">
                                                <a href="{{ route('payslips.generated') }}?payslip={{ $row['id'] }}" class="pd-open"
                                                   title="Open payslip {{ $row['payslip_number'] }}"><i class="fa fa-arrow-right"></i></a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- The list behind a card --}}
    <div class="modal fade" id="pd-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header pd-modal-head">
                    <div>
                        <h5 class="modal-title mb-0" id="pd-title">—</h5>
                        <p class="mb-0 f-12" id="pd-range"></p>
                    </div>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-0">
                    <div class="p-3 border-bottom d-flex flex-wrap align-items-center" style="gap: 10px">
                        <div id="pd-summary" class="d-flex flex-wrap mr-auto" style="gap: 16px"></div>
                        <input type="search" id="pd-search" class="form-control form-control-sm" placeholder="Search employee" style="width: 200px">
                        <button type="button" class="btn btn-sm btn-success" id="pd-csv"><i class="fa fa-download mr-1"></i>Export CSV</button>
                    </div>
                    <div class="table-responsive" style="max-height: 62vh">
                        <table class="table table-hover mb-0 f-13 pd-table">
                            <thead id="pd-head"></thead>
                            <tbody id="pd-rows"></tbody>
                            <tfoot id="pd-foot"></tfoot>
                        </table>
                    </div>
                    <p class="p-4 mb-0 text-center f-13 text-lightest" id="pd-empty" style="display:none">Nothing in this list for the dates chosen.</p>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            var query = {
                from: '{{ $from->toDateString() }}',
                to: '{{ $to->toDateString() }}',
                include_drafts: {{ $includeDrafts ? 1 : 0 }}
            };
            var rangeText = '{{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}';
            var current = null;

            function esc(value) { return $('<div>').text(value == null ? '' : value).html(); }
            function money(value) {
                return '₹' + (Math.round((value || 0) * 100) / 100).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            function cell(value, type) {
                if (type === 'money') { return money(value); }
                if (type === 'num') { return (Math.round((value || 0) * 100) / 100).toLocaleString('en-IN'); }
                return esc(value);
            }

            // Rows matching the search, with a total under every figure column.
            function draw() {
                var term = ($('#pd-search').val() || '').toLowerCase();
                var cols = current.columns;
                var rows = current.rows.filter(function (row) {
                    return !term || (row[0] + ' ' + row[1] + ' ' + row[2]).toLowerCase().indexOf(term) !== -1;
                });
                var sums = cols.map(function () { return 0; });

                $('#pd-rows').html(rows.map(function (row, i) {
                    return '<tr><td>' + (i + 1) + '</td>' + row.map(function (value, c) {
                        if (cols[c][1] !== 'text') { sums[c] += Number(value) || 0; }
                        return '<td class="' + (cols[c][1] === 'text' ? '' : 'text-right') + '">' + cell(value, cols[c][1]) + '</td>';
                    }).join('') + '</tr>';
                }).join(''));

                $('#pd-foot').html(rows.length ? '<tr><td></td>' + cols.map(function (col, c) {
                    return c === 0 ? '<td>TOTAL</td>' : '<td class="text-right">' + (col[1] === 'text' ? '' : cell(sums[c], col[1])) + '</td>';
                }).join('') + '</tr>' : '');

                $('#pd-summary').html('<span class="f-12 text-lightest">' + rows.length + ' record(s)</span>' + cols.map(function (col, c) {
                    return col[1] === 'money' ? '<span class="f-12 text-lightest">' + esc(col[0]) + ': <strong class="text-dark">' + money(sums[c]) + '</strong></span>' : '';
                }).join(''));
                $('#pd-empty').toggle(!rows.length);
            }

            function open(card, title) {
                current = null;
                $('#pd-title').text(title);
                $('#pd-range').text(rangeText);
                $('#pd-head, #pd-rows, #pd-foot, #pd-summary').empty();
                $('#pd-search').val('');
                $('#pd-empty').hide();
                $('#pd-modal').modal('show');

                $.get('{{ route('payroll-dashboard.table') }}', $.extend({ card: card }, query)).done(function (data) {
                    current = data;
                    $('#pd-title').text(data.title || title);
                    $('#pd-head').html('<tr><th>#</th>' + data.columns.map(function (col) {
                        return '<th class="' + (col[1] === 'text' ? '' : 'text-right') + '">' + esc(col[0]) + '</th>';
                    }).join('') + '</tr>');
                    draw();
                }).fail(function () {
                    $('#pd-empty').text('That list could not be loaded just now.').show();
                });
            }

            $(document).on('click', '[data-card]', function () { open($(this).data('card'), $(this).data('title')); });
            $(document).on('keydown', '[data-card]', function (e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open($(this).data('card'), $(this).data('title')); }
            });
            $('#pd-search').on('input', function () { if (current) { draw(); } });

            $('#pd-csv').on('click', function () {
                if (!current || !current.rows.length) { return; }
                var quote = function (v) { v = v == null ? '' : String(v); return /[",\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; };
                var csv = [current.columns.map(function (c) { return quote(c[0]); }).join(',')]
                    .concat(current.rows.map(function (row) { return row.map(quote).join(','); })).join('\n');
                var link = document.createElement('a');
                link.href = URL.createObjectURL(new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' }));
                link.download = (current.title || 'payroll').replace(/[^a-z0-9]+/gi, '-').toLowerCase() + '-' + query.from + '-to-' + query.to + '.csv';
                link.click();
            });

            // Statutory files: the period picked, drafts as on the dashboard.
            $('.pd-download').on('click', function () {
                var parts = ($('#pd-return-period').val() || '').split('|');
                var what = $(this).data('what');
                var params = { month: parts[0], year: parts[1], include_drafts: query.include_drafts };
                var url = what === 'pf' ? '{{ route('statutory-returns.pf') }}'
                    : what === 'bundle' ? '{{ route('statutory-returns.bundle') }}'
                    : '{{ route('statutory-returns.spreadsheet') }}';

                if (what.indexOf('sheet:') === 0) { params.which = what.split(':')[1]; }

                window.location = url + '?' + $.param(params);
            });
        });
    </script>
@endpush
