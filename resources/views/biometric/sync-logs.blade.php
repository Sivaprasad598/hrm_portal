@extends('layouts.app')

@section('content')
    {{-- Standard page gutter, matching the rest of the app --}}
    <div class="content-wrapper arw-dash">

        @include('biometric.nav', ['active' => $activeTab])

        {{-- Connection state. The settings screen that edits this arrives in a
             later phase; until then this reports what is configured. --}}
        <div class="row mb-3">
            <div class="col-md-4 mb-2">
                <div class="card bg-white border-0 b-shadow-4">
                    <div class="card-body p-3">
                        <p class="mb-1 f-12 text-lightest text-uppercase">Connection</p>
                        @if ($setting && $setting->isReadyToSync())
                            <h5 class="mb-0 f-16 text-primary">Enabled</h5>
                        @elseif ($setting && $setting->isConfigured())
                            <h5 class="mb-0 f-16 text-yellow">Configured, sync off</h5>
                        @else
                            <h5 class="mb-0 f-16 text-red">Not configured</h5>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-2">
                <div class="card bg-white border-0 b-shadow-4">
                    <div class="card-body p-3">
                        <p class="mb-1 f-12 text-lightest text-uppercase">Written to Attendance</p>
                        <h4 class="mb-0 f-21 text-dark-grey">{{ $attendanceRowCount }}</h4>
                        <p class="mb-0 f-11 text-lightest">by biometric sync</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-2">
                <div class="card bg-white border-0 b-shadow-4">
                    <div class="card-body p-3">
                        <p class="mb-1 f-12 text-lightest text-uppercase">Unmatched Employees</p>
                        <h4 class="mb-0 f-21 {{ $unmatchedCount > 0 ? 'text-red' : 'text-dark-grey' }}">
                            {{ $unmatchedCount }}
                        </h4>
                    </div>
                </div>
            </div>
        </div>

        @if ($setting && $setting->last_sync_at)
            <p class="f-13 text-lightest mb-3">
                Last sync {{ $setting->last_sync_at->diffForHumans() }}
                @if ($setting->last_sync_message)
                    &mdash; {{ $setting->last_sync_message }}
                @endif
            </p>
        @endif

        <x-cards.data padding="false">
            <div class="table-responsive">
                <table class="table table-hover border-0 mb-0">
                    <thead>
                        <tr>
                            <th class="f-12">@lang('app.type')</th>
                            <th class="f-12">Range</th>
                            <th class="f-12">Trigger</th>
                            <th class="f-12">Fetched</th>
                            <th class="f-12">Created</th>
                            <th class="f-12">Updated</th>
                            <th class="f-12">Promoted</th>
                            <th class="f-12">Unmatched</th>
                            <th class="f-12">Duration</th>
                            <th class="f-12">@lang('app.status')</th>
                            <th class="f-12">@lang('app.createdAt')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td class="f-13">{{ ucwords(str_replace('_', ' ', $log->sync_type)) }}</td>
                                <td class="f-13">
                                    @if ($log->from_date)
                                        {{ $log->from_date->format(company()->date_format) }}
                                        &ndash;
                                        {{ $log->to_date?->format(company()->date_format) }}
                                    @else
                                        --
                                    @endif
                                </td>
                                <td class="f-13">
                                    {{ ucfirst($log->trigger) }}
                                    @if ($log->triggeredBy)
                                        <span class="text-lightest f-11">({{ $log->triggeredBy->name }})</span>
                                    @endif
                                </td>
                                <td class="f-13">{{ $log->records_fetched }}</td>
                                <td class="f-13">{{ $log->records_created }}</td>
                                <td class="f-13">{{ $log->records_updated }}</td>
                                <td class="f-13">{{ $log->records_promoted }}</td>
                                <td class="f-13">
                                    @if ($log->records_unmatched > 0)
                                        <span class="text-red">{{ $log->records_unmatched }}</span>
                                    @else
                                        0
                                    @endif
                                </td>
                                <td class="f-13">{{ $log->duration_for_humans }}</td>
                                <td>
                                    <span class="badge badge-{{ $log->status_color }} f-11">
                                        {{ ucfirst($log->status) }}
                                    </span>
                                </td>
                                <td class="f-13">
                                    {{ $log->started_at?->format(company()->date_format . ' ' . company()->time_format) ?: '--' }}
                                </td>
                            </tr>

                            @if (!empty($log->unmatched_codes))
                                <tr>
                                    <td colspan="11" class="f-12 text-lightest pt-0">
                                        Unmatched codes: {{ implode(', ', array_slice($log->unmatched_codes, 0, 25)) }}
                                        @if (count($log->unmatched_codes) > 25)
                                            &hellip; +{{ count($log->unmatched_codes) - 25 }} more
                                        @endif
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="11" class="text-center py-5">
                                    <p class="mb-1 f-15 text-dark-grey">@lang('messages.noRecordFound')</p>
                                    <p class="mb-0 f-13 text-lightest">
                                        Sync runs are recorded here.
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($logs->hasPages())
                <div class="p-3">
                    {{ $logs->links() }}
                </div>
            @endif

        </x-cards.data>
    </div>

@endsection
