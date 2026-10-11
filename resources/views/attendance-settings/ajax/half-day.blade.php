<div class="halfday-scope">
    <div class="container">
        <h2>Half Day Settings</h2>

        <div class="form-group">
            <label>Number of Late Comings</label>
            <input type="number" id="lateCount" min="1" placeholder="Enter Late Comings" value="{{ $attendanceSetting->half_day_late_threshold }}">
        </div>

        <div class="form-group">
            <label>Deduction Type</label>
            <select id="deduction">
                <option value="0.5" {{ (string) $attendanceSetting->half_day_deduction_type === '0.5' ? 'selected' : '' }}>Half Day (0.5)</option>
                <option value="1" {{ (string) $attendanceSetting->half_day_deduction_type === '1.0' ? 'selected' : '' }}>Full Day (1)</option>
            </select>
        </div>

        <button id="saveHalfDay">Save</button>
    </div>
</div>

<style>
    .halfday-scope {
        font-family: Arial, Helvetica, sans-serif;
        background: #f4f6f9;
        padding: 30px 0;
    }

    .halfday-scope .container {
        width: 500px;
        max-width: 100%;
        margin: 0 auto;
        background: #fff;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 0 10px rgba(0, 0, 0, .15);
    }

    .halfday-scope h2 {
        text-align: center;
        color: #007bff;
        margin-bottom: 20px;
    }

    .halfday-scope .form-group {
        margin-bottom: 18px;
    }

    .halfday-scope label {
        font-weight: bold;
        display: block;
        margin-bottom: 8px;
    }

    .halfday-scope input,
    .halfday-scope select {
        width: 100%;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 5px;
        font-size: 15px;
    }

    .halfday-scope button {
        width: 100%;
        padding: 12px;
        border: none;
        background: #007bff;
        color: #fff;
        font-size: 16px;
        border-radius: 5px;
        cursor: pointer;
    }

    .halfday-scope button:hover {
        background: #0056b3;
    }
</style>

<script>
    (function () {
        var scope = document.querySelector('.halfday-scope');

        if (!scope) {
            return;
        }

        var saveBtn = scope.querySelector('#saveHalfDay');
        var lateCountInput = scope.querySelector('#lateCount');
        var deductionSelect = scope.querySelector('#deduction');

        saveBtn.addEventListener('click', function () {
            $.easyAjax({
                url: "{{ route('attendance-settings.update_half_day') }}",
                type: 'POST',
                container: '.halfday-scope',
                blockUI: true,
                disableButton: true,
                buttonSelector: '#saveHalfDay',
                data: {
                    _token: "{{ csrf_token() }}",
                    half_day_late_threshold: lateCountInput.value,
                    half_day_deduction_type: deductionSelect.value
                }
            });
        });
    })();
</script>
