{{-- One area of a role's access: its pages, each with a switch and its actions. --}}
@php $onCount = collect($area['pages'])->where('on', true)->count(); @endphp
<div class="acc-area">
    <div class="acc-area-head">
        <h5>{{ $area['label'] }}</h5>
        <span>{{ $onCount }} of {{ count($area['pages']) }} page{{ count($area['pages']) === 1 ? '' : 's' }} on</span>
    </div>

    @foreach ($area['pages'] as $page)
        <div class="acc-row {{ $page['on'] || $locked ? '' : 'off' }}">
            <div class="acc-name">{{ $page['name'] }}</div>

            <div class="custom-control custom-switch" title="{{ $page['on'] ? 'In the sidebar' : 'Not in the sidebar' }}">
                <input type="checkbox" class="custom-control-input acc-page" id="page-{{ $page['id'] }}"
                       data-role="{{ $role->id }}" data-menu="{{ $page['id'] }}"
                       @checked($page['on'] || $locked) @disabled($locked)>
                <label class="custom-control-label" for="page-{{ $page['id'] }}"></label>
            </div>

            <div class="acc-actions">
                @forelse ($page['actions'] as $action)
                    @php $isOn = $locked || $action['level'] !== 'none'; @endphp
                    <label class="acc-action {{ $isOn ? 'is-on' : '' }}" title="{{ $action['name'] }}">
                        @if ($action['switch'])
                            <input type="checkbox" class="acc-perm" data-role="{{ $role->id }}" data-permission="{{ $action['id'] }}"
                                   data-on="{{ $allId }}" data-off="{{ $noneId }}" @checked($isOn) @disabled($locked)>
                            <span>{{ $action['label'] }}</span>
                        @else
                            <span>{{ $action['label'] }}</span>
                            <select class="acc-perm" data-role="{{ $role->id }}" data-permission="{{ $action['id'] }}" @disabled($locked)>
                                @foreach ($action['options'] as $id => $words)
                                    <option value="{{ $id }}" @selected($locked ? $id == $allId : $id == $action['level_id'])>{{ $words }}</option>
                                @endforeach
                            </select>
                        @endif
                    </label>
                @empty
                    <span class="acc-none">Open to anyone who has the page</span>
                @endforelse
            </div>
        </div>
    @endforeach
</div>
