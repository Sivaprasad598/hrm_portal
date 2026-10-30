<style>
    .mt{
        margin-top: -4px;
    }
</style>

<div class="row">
    <div class="col-sm-12">
        <x-form id="save-department-data-form" method="PUT">
            <div class="add-client bg-white rounded">
                <h4 class="mb-0 p-20 f-21 font-weight-normal  border-bottom-grey">
                    {{ $department->parent_id ? 'Edit Sub Division' : 'Edit Division' }}</h4>
                <div class="row p-20">
                    <div class="col-md-6">
                        @if (\App\Models\Team::where('parent_id', $department->id)->exists())
                            {{-- A division with sub divisions stays a division --}}
                            <x-forms.label class="my-3" fieldId="parent_label" fieldLabel="Division" fieldName="parent_label"></x-forms.label>
                            <p class="f-14 text-dark-grey mb-0">Main division (has sub divisions)</p>
                            <input type="hidden" name="parent_id" id="parent_id" value="">
                        @else
                            <x-forms.label class="my-3" fieldId="parent_label" fieldLabel="Division" fieldName="parent_label"></x-forms.label>
                            <x-forms.input-group>
                                <select class="form-control select-picker mt" name="parent_id" id="parent_id" data-live-search="true">
                                    <option value="">-- None (main division) --</option>
                                    @foreach($departments->whereNull('parent_id')->sortBy('team_name') as $item)
                                        <option value="{{ $item->id }}" @selected($department->parent_id == $item->id)>{{ $item->team_name }}</option>
                                    @endforeach
                                </select>
                            </x-forms.input-group>
                        @endif
                        <small class="text-dark-grey d-block mt-1" id="division-kind"></small>
                    </div>
                    <div class="col-md-6">
                        <x-forms.text fieldId="team_name" fieldLabel="Division Name" fieldName="team_name"
                            fieldRequired="true" :fieldValue="$department->team_name">
                        </x-forms.text>
                    </div>
                    {{-- Department head: approves this department's leave (Leave Settings -> approval steps). --}}
                    <div class="col-md-6">
                        <x-forms.label class="my-3" fieldId="head_user_id" fieldLabel="Head"></x-forms.label>
                        <x-forms.input-group>
                            <select class="form-control select-picker" name="head_user_id" id="head_user_id" data-live-search="true" data-size="8">
                                <option value="">--</option>
                                @foreach (\App\Models\User::allEmployees(null, true) as $headCandidate)
                                    <option value="{{ $headCandidate->id }}" @selected(($department->head_user_id ?? null) == $headCandidate->id)>{{ $headCandidate->name }}{{ $headCandidate->designation_name ? ' · ' . $headCandidate->designation_name : '' }}</option>
                                @endforeach
                            </select>
                        </x-forms.input-group>
                    </div>
                    <div class="col-md-12">
                        <input type="hidden" name="consider_attendance_hours" value="0">
                        <div class="custom-control custom-switch mt-3">
                            <input type="checkbox" class="custom-control-input" id="consider_attendance_hours" name="consider_attendance_hours" value="1" @checked($department->consider_attendance_hours ?? true)>
                            <label class="custom-control-label f-14" for="consider_attendance_hours">Consider attendance hours</label>
                        </div>
                        <small class="text-dark-grey d-block mt-1">Off: for this department any punch on a working day is a full day (no hour minimum, no lates, no overtime). Days with no punch and no leave still count as absent.</small>
                    </div>
                </div>

                <x-form-actions>
                    <x-forms.button-primary id="save-department-form" class="mr-3" icon="check">@lang('app.save')
                    </x-forms.button-primary>
                    <x-forms.button-cancel :link="route('departments.index')" class="border-0">@lang('app.cancel')
                    </x-forms.button-cancel>
                </x-form-actions>
            </div>
        </x-form>

    </div>
</div>

<script>
    // Picking a division makes this a sub division of it; none, a division of its own.
    function divisionLabels() {
        var sub = $('#parent_id').val() !== '';
        var label = $('label[for="designation_name"], label[for="team_name"]').contents().filter(function () { return this.nodeType === 3 && this.textContent.trim() !== ''; }).first()[0];
        if (label) { label.textContent = sub ? 'Sub Division Name ' : 'Division Name '; }
        $('#division-kind').text(sub ? 'Adding a sub division under the selected division.' : 'No division selected: this is a main division.');
    }
    $('#parent_id').on('change', divisionLabels);
    divisionLabels();

    $(document).ready(function() {

        $('#save-department-form').click(function() {

            const url = "{{ route('departments.update', $department->id) }}";

            $.easyAjax({
                url: url,
                container: '#save-department-data-form',
                type: "POST",
                disableButton: true,
                blockUI: true,
                buttonSelector: "#save-department-form",
                data: $('#save-department-data-form').serialize(),
                success: function(response) {
                    window.location.href = response.redirectUrl;
                }
            });
        });

        init(RIGHT_MODAL);
    });
</script>
