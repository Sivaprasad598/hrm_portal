{{-- One role's access (AccessSettingsController): area by area, each page with
     its switch and the actions that belong to it. Redrawn after every change. --}}
@php
    $main = collect($areas)->where('other', false);
    $other = collect($areas)->where('other', true);
    $levelId = fn ($name) => \Illuminate\Support\Facades\DB::table('permission_types')->where('name', $name)->value('id');
    $allId = $levelId('all');
    $noneId = $levelId('none');
@endphp

@if ($locked)
    <div class="alert alert-info f-13">The <strong>Super Admin</strong> always has every page and every action. Shown here for reference; nothing can be switched off.</div>
@endif

@php
    $renderArea = function ($area) use ($role, $locked, $allId, $noneId) {
        return view('access-settings.area', compact('area', 'role', 'locked', 'allId', 'noneId'))->render();
    };
@endphp

@foreach ($main as $area)
    {!! $renderArea($area) !!}
@endforeach

@if ($other->isNotEmpty())
    <details class="acc-other mb-3">
        <summary>Other modules ({{ $other->count() }}) — Leads, Clients, Work, Finance, Tickets, Reports … not used by the HR portal</summary>
        @foreach ($other as $area)
            {!! $renderArea($area) !!}
        @endforeach
    </details>
@endif

{{-- Who changed what --}}
<div class="acc-area acc-history">
    <div class="acc-area-head"><h5>Recent changes to {{ $role->name === 'admin' ? 'Super Admin' : $role->display_name }}</h5></div>
    @if ($history->isNotEmpty())
        <table class="table mb-0">
            <tbody>
                @foreach ($history as $h)
                    <tr>
                        <td class="text-nowrap text-lightest">{{ \Carbon\Carbon::parse($h->created_at)->timezone(company()->timezone)->format('d M Y H:i') }}</td>
                        <td>{{ $h->by ?: 'System' }}</td>
                        <td>{{ $h->kind === 'page' ? 'Page' : 'Action' }}: <strong>{{ $h->subject }}</strong></td>
                        <td class="text-nowrap">{{ \App\Services\Access\AccessCatalog::levelWords($h->from_value) }} &rarr; {{ \App\Services\Access\AccessCatalog::levelWords($h->to_value) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="f-13 text-lightest p-3 mb-0">No changes made on this screen yet.</p>
    @endif
</div>
