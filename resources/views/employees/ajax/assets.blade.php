@php
    $fmt = company()->date_format;
    $isMe = $employee->id == user()->id;
    $overdue = $heldAssets->filter(fn ($a) => $a->isOverdue())->count();
    $recovered = (float) $pastAssets->sum('recovery_amount');
    // An icon per kind of asset, guessed from its type name
    $icon = function ($type) {
        $t = strtolower((string) $type);
        foreach (['laptop' => 'laptop', 'desktop' => 'desktop', 'monitor' => 'desktop', 'mobile' => 'mobile-alt', 'phone' => 'mobile-alt',
                  'sim' => 'sim-card', 'card' => 'id-card', 'key' => 'key', 'mouse' => 'mouse', 'keyboard' => 'keyboard',
                  'headset' => 'headphones', 'head' => 'headphones', 'bag' => 'briefcase', 'vehicle' => 'car', 'car' => 'car', 'bike' => 'motorcycle'] as $k => $i) {
            if (str_contains($t, $k)) { return $i; }
        }
        return 'box';
    };
@endphp

<style>
    .ast-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 14px; }
    .ast-item { border: 1px solid var(--border-color, #e2e8f0); border-radius: var(--card-radius, 12px); padding: 16px; background: #fff; display: flex; flex-direction: column; gap: 12px; }
    .ast-item.is-overdue { border-color: #fca5a5; }
    .ast-top { display: flex; gap: 12px; align-items: flex-start; }
    .ast-ico { flex: 0 0 42px; width: 42px; height: 42px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 18px;
        background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 12%, #fff); color: var(--epic-blue, #cb6b4f); }
    .ast-name { font-size: 14.5px; font-weight: 600; color: var(--epic-text, #212529); line-height: 1.3; }
    .ast-code { font-size: 12px; color: var(--epic-muted, #878a99); }
    .ast-facts { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 14px; margin: 0; }
    .ast-facts dt { font-size: 11.5px; color: var(--epic-muted, #99a5b5); font-weight: 500; }
    .ast-facts dd { margin: 1px 0 0; font-size: 13px; color: var(--epic-text, #212529); word-break: break-word; }
    .ast-actions { display: flex; justify-content: flex-end; border-top: 1px dashed var(--epic-line, #e8e9e9); padding-top: 10px; }
</style>

<div class="card bg-white border-0 b-shadow-4">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-start flex-wrap pt-4">
        <div>
            <h4 class="f-18 f-w-500 mb-0">{{ $isMe ? 'My Assets' : 'Assets' }}</h4>
            <p class="ms-intro">{{ $isMe ? 'Company equipment issued to you. Contact HR if something here is not with you, or is damaged.' : 'Company equipment issued to ' . $employee->name . '.' }}</p>
        </div>
        <div class="mt-1">
            @if ($manageAssets && $heldAssets->isNotEmpty())
                <a href="{{ route('assets.handover', ['user' => $employee->id]) }}" target="_blank" class="btn btn-secondary rounded f-14 mr-1">
                    <i class="fa fa-print mr-1"></i>Print Acknowledgement</a>
            @endif
            @if ($manageAssets && $employee->status === 'active')
                <a href="{{ route('assets.assign-form', ['user_id' => $employee->id]) }}" class="btn btn-primary rounded f-14 openRightModal">
                    <i class="fa fa-user-plus mr-1"></i>Assign Asset</a>
            @endif
        </div>
    </div>
    <div class="card-body pt-2">
    <div class="ms-stats">
        <div class="ms-stat">
            <div class="lbl"><span class="ic" style="--ic-bg:#cb6b4f;--ic-fg:#fff"><i class="fa fa-laptop"></i></span>With {{ $isMe ? 'you' : 'them' }} now</div>
            <div class="val">{{ $heldAssets->count() }}</div>
            <div class="sub">{{ $heldAssets->count() === 1 ? 'asset' : 'assets' }} currently issued</div>
        </div>
        <div class="ms-stat {{ $overdue ? 'is-warn' : '' }}">
            <div class="lbl"><span class="ic" style="--ic-bg:#fdecec;--ic-fg:#b42318"><i class="fa fa-clock"></i></span>Return overdue</div>
            <div class="val">{{ $overdue }}</div>
            <div class="sub">{{ $overdue ? 'past the expected return date' : 'nothing overdue' }}</div>
        </div>
        <div class="ms-stat">
            <div class="lbl"><span class="ic" style="--ic-bg:#e7f6ec;--ic-fg:#15803d"><i class="fa fa-undo"></i></span>Returned earlier</div>
            <div class="val">{{ $pastAssets->count() }}</div>
            <div class="sub">{{ $recovered ? '₹' . number_format($recovered, 2) . ' recovered' : 'handed back' }}</div>
        </div>
    </div>

    <div class="ms-sec"><h5>With {{ $isMe ? 'you' : $employee->name }} now</h5></div>
    @if ($heldAssets->isEmpty())
        <div class="ms-empty">
            <i class="fa fa-box-open"></i>
            <b>No assets issued{{ $isMe ? ' to you' : '' }}</b>
            {{ $isMe ? 'When HR issues you a laptop, ID card or other equipment, it shows up here.' : 'Use Assign Asset to issue equipment.' }}
        </div>
    @else
        <div class="ast-grid">
            @foreach ($heldAssets as $a)
                <div class="ast-item {{ $a->isOverdue() ? 'is-overdue' : '' }}">
                    <div class="ast-top">
                        <span class="ast-ico"><i class="fa fa-{{ $icon($a->asset?->type?->name) }}"></i></span>
                        <div class="flex-grow-1">
                            @if ($manageAssets)
                                <a href="{{ route('assets.show', $a->asset_id) }}" class="openRightModal ast-name">{{ $a->asset?->name }}</a>
                            @else
                                <div class="ast-name">{{ $a->asset?->name }}</div>
                            @endif
                            <div class="ast-code">{{ $a->asset?->asset_code }}{{ $a->asset?->type?->name ? ' · ' . $a->asset->type->name : '' }}</div>
                        </div>
                        @if ($a->isOverdue())<span class="badge badge-danger">Overdue</span>@endif
                    </div>
                    <dl class="ast-facts">
                        <div><dt>Serial number</dt><dd>{{ $a->asset?->serial_number ?: '--' }}</dd></div>
                        <div><dt>Condition when issued</dt><dd>{{ \App\Models\Asset::CONDITIONS[$a->condition_out] ?? ($a->condition_out ?: '--') }}</dd></div>
                        <div><dt>Issued on</dt><dd>{{ $a->assigned_on->format($fmt) }}</dd></div>
                        <div><dt>Return by</dt><dd class="{{ $a->isOverdue() ? 'text-danger font-weight-600' : '' }}">{{ $a->expected_return_on ? $a->expected_return_on->format($fmt) : 'No date set' }}</dd></div>
                    </dl>
                    @if ($manageAssets)
                        <div class="ast-actions">
                            <a href="{{ route('assets.return-form', $a->id) }}" class="btn btn-sm btn-outline-primary openRightModal"><i class="fa fa-undo mr-1"></i>Take Return</a>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if ($pastAssets->isNotEmpty())
        <div class="ms-sec mt-4"><h5>Returned <span>equipment handed back earlier</span></h5></div>
        <div class="table-responsive">
            <table class="ms-table">
                <thead><tr><th>Asset</th><th>Issued</th><th>Returned</th><th>Condition on return</th><th class="text-right">Recovered</th></tr></thead>
                <tbody>
                @foreach ($pastAssets as $a)
                    <tr>
                        <td><b>{{ $a->asset?->name }}</b> <div class="muted">{{ $a->asset?->asset_code }}</div></td>
                        <td>{{ $a->assigned_on->format($fmt) }}</td>
                        <td>{{ $a->returned_on->format($fmt) }}</td>
                        <td>{{ $a->condition_in === 'lost' ? 'Lost' : (\App\Models\Asset::CONDITIONS[$a->condition_in] ?? ($a->condition_in ?: '--')) }}</td>
                        <td class="text-right">{{ $a->recovery_amount ? '₹' . number_format((float) $a->recovery_amount, 2) : '--' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
</div>
