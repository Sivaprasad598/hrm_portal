@extends('layouts.app')

@section('content')
<div class="content-wrapper">
    {{-- Main Card --}}
    <div class="card mb-4">
        {{-- Card Header --}}
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap py-3" style="gap: 15px;">
            <div>
                <h2 class="h5 mb-1">Lead Team</h2>
                <div class="text-muted small">The lead, its owner and the employees assigned to it.</div>
            </div>

            <div class="d-flex align-items-center" style="gap: 12px;">
                {{-- Sub-tabs (Pills) --}}
                <div class="btn-group btn-group-toggle" data-toggle="buttons">
                    <label class="btn btn-sm btn-outline-primary active">
                        <i class="fa fa-users mr-1"></i> Lead Team
                    </label>
                    <label class="btn btn-sm btn-outline-secondary">
                        <i class="fa fa-comments mr-1"></i> Team Chat
                    </label>
                    <label class="btn btn-sm btn-outline-secondary">
                        <i class="fa fa-calendar mr-1"></i> Lead Team Meetings
                        <span class="badge badge-light border ml-1">3</span>
                    </label>
                </div>

                {{-- Manage Team Button --}}
                <button type="button" class="btn btn-sm btn-outline-secondary">
                    <i class="fa fa-user-cog mr-1"></i> Manage team
                </button>
            </div>
        </div>

        {{-- Table --}}
        <div class="table-responsive">
            <table class="table table-hover mb-0" style="font-size: 14px;">
                <thead class="bg-light text-muted" style="font-size: 12px; text-transform: uppercase;">
                    <tr>
                        <th style="width: 40px; padding-left: 20px;">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="selectAllTeam" checked>
                                <label class="custom-control-label" for="selectAllTeam"></label>
                            </div>
                        </th>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Designation</th>
                        <th>Email</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($team as $person)
                    <tr>
                        <td style="padding-left: 20px;">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input team-member-checkbox"
                                       id="teamMember-{{ $person['key'] ?? $loop->index }}"
                                       value="{{ $person['key'] ?? '' }}"
                                       checked>
                                <label class="custom-control-label" for="teamMember-{{ $person['key'] ?? $loop->index }}"></label>
                            </div>
                        </td>
                        <td class="align-middle">{{ $person['name'] ?? 'Unknown' }}</td>
                        <td class="align-middle">
                            <span class="badge badge-light border text-dark">{{ $person['role'] ?? 'Member' }}</span>
                        </td>
                        <td class="align-middle">{{ $person['designation'] ?? '--' }}</td>
                        <td class="align-middle">
                            @if(isset($person['email']) && $person['email'] !== '--')
                                <a href="mailto:{{ $person['email'] }}">{{ $person['email'] }}</a>
                            @else
                                --
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">No team members assigned yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        // Select All functionality
        $('#selectAllTeam').on('change', function() {
            $('.team-member-checkbox').prop('checked', $(this).prop('checked'));
        });

        // If one checkbox is unchecked, uncheck the "Select All"
        $('.team-member-checkbox').on('change', function() {
            if (!this.checked) {
                $('#selectAllTeam').prop('checked', false);
            } else if ($('.team-member-checkbox:checked').length === $('.team-member-checkbox').length) {
                $('#selectAllTeam').prop('checked', true);
            }
        });
    });
</script>
@endpush
