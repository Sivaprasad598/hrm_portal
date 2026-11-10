{{--
    The round plan editor: rows of rounds in order, posted as rounds[n][...]
    (RecruitBaseController::planRows). $plan: RecruitRound rows; $employees:
    who can take a round; $planId: a unique id for this editor on the page.
--}}
@php
    $planId = $planId ?? 'round-plan';
    $rowsData = collect($plan ?? [])->values();
@endphp

<div id="{{ $planId }}" class="round-plan">
    <div class="rp-rows">
        @foreach ($rowsData as $i => $r)
            @include('recruitment.partials.round-plan-row', ['idx' => $i, 'r' => $r])
        @endforeach
    </div>

    <template class="rp-template">
        @include('recruitment.partials.round-plan-row', ['idx' => '__IDX__', 'r' => null])
    </template>

    <button type="button" class="btn btn-sm btn-outline-primary rp-add mt-1"><i class="fa fa-plus mr-1"></i>Add round</button>
    <p class="f-12 text-dark-grey mt-2 mb-0">
        <strong>Test / exam:</strong> marks out of the total; at or over the cut-off clears it, under it rejects the candidate.
        <strong>Face-to-face:</strong> each person on the panel scores 1–5; the average at or over the pass mark clears it.
        Leave the cut-off / pass mark empty for HR to decide that round.
    </p>
</div>

<style>
    .round-plan .rp-row { border: 1px solid var(--epic-line, #e8e9e9); border-radius: 8px; padding: 12px 12px 4px; margin-bottom: 10px; background: #fff; }
    .round-plan .rp-no { width: 26px; height: 26px; border-radius: 50%; background: var(--epic-blue); color: #fff; font-size: 12px; font-weight: 700;
        display: inline-flex; align-items: center; justify-content: center; flex: 0 0 26px; }
    .round-plan label { font-size: 12px; color: var(--epic-muted, #878a99); margin-bottom: 2px; }
    .round-plan .rp-tools a { color: var(--epic-muted, #878a99); margin-left: 8px; }
    .round-plan .rp-tools a:hover { color: var(--epic-blue); }
</style>

<script>
    // On a full page this runs before jQuery / bootstrap-select are loaded (they come at the
    // end of the layout); in a side panel they are already there.
    (function (boot) {
        if (window.jQuery && jQuery.fn.selectpicker) { boot(); } else { document.addEventListener('DOMContentLoaded', boot); }
    })(function () {
        var $plan = $('#{{ $planId }}');
        var next = {{ $rowsData->count() }};

        function renumber() {
            $plan.find('.rp-row').each(function (i) { $(this).find('.rp-no').text(i + 1); });
        }

        function setType($row) {
            var test = $row.find('.rp-type').val() === 'test';
            $row.find('.rp-test-only').toggleClass('d-none', !test);
            $row.find('.rp-interview-only').toggleClass('d-none', test);
            $row.find('.rp-cut-label').text(test ? 'Cut-off (marks)' : 'Pass mark (of 5)');
            $row.find('.rp-panel-label').text(test ? 'Conducted / marked by' : 'Interview panel');
        }

        function init($row) {
            $row.find('select.rp-panel').selectpicker();
            setType($row);
        }

        $plan.find('.rp-row').each(function () { init($(this)); });

        $plan.on('click', '.rp-add', function () {
            var html = $plan.find('.rp-template').html().replace(/__IDX__/g, 'n' + (next++));
            var $row = $(html);
            $plan.find('.rp-rows').append($row);
            init($row);
            renumber();
        });

        $plan.on('click', '.rp-remove', function () {
            if ($plan.find('.rp-row').length <= 1) { return; }
            $(this).closest('.rp-row').remove();
            renumber();
        });

        $plan.on('click', '.rp-up', function () {
            var $row = $(this).closest('.rp-row');
            $row.prev('.rp-row').before($row);
            renumber();
        });

        $plan.on('click', '.rp-down', function () {
            var $row = $(this).closest('.rp-row');
            $row.next('.rp-row').after($row);
            renumber();
        });

        $plan.on('change', '.rp-type', function () { setType($(this).closest('.rp-row')); });

        renumber();
    });
</script>
