{{-- A Department dropdown narrowing an employee picker to that department
     (QA TC-0016/0017). Pass the two select ids: $department and $employee.
     Options carry data-department; "All" (empty value) is always kept. --}}
<script>
    (function () {
        var dept = '#{{ $department }}', emp = '#{{ $employee }}';

        function narrow() {
            var chosen = String($(dept).val() || '');

            $(emp).find('option').each(function () {
                var keep = !this.value || !chosen || String($(this).data('department') || '') === chosen;
                $(this).prop('hidden', !keep).prop('disabled', !keep);

                if (!keep) {
                    $(this).prop('selected', false);
                }
            });

            if ($.fn.selectpicker) {
                $(emp).selectpicker('refresh');
            }
        }

        $(document).ready(function () {
            $(dept).on('change', narrow);
            narrow();
        });
    })();
</script>
