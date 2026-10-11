@php
    $weekDayLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    // icon + CSS status-class per key, matching the provided design exactly.
    $statusMeta = [
        'holiday' => ['icon' => '★', 'class' => 'status-holiday'],
        'dayoff' => ['icon' => '⬛', 'class' => 'status-dayoff'],
        'present' => ['icon' => '✓', 'class' => 'status-present'],
        'wfh' => ['icon' => '🏠', 'class' => 'status-wfh'],
        'halfday' => ['icon' => '◐', 'class' => 'status-halfday'],
        'late' => ['icon' => '🟡', 'class' => 'status-late'],
        'absent' => ['icon' => '×', 'class' => 'status-absent'],
        'leave' => ['icon' => '🚩', 'class' => 'status-leave'],
        'cl' => ['icon' => 'CL', 'class' => 'status-cl'],
        'sl' => ['icon' => 'SL', 'class' => 'status-sl'],
        'lop' => ['icon' => '⚠️', 'class' => 'status-lop'],
        'cl-half' => ['icon' => 'CL (0.5)', 'class' => 'status-cl-half'],
        'sl-half' => ['icon' => 'SL (0.5)', 'class' => 'status-sl-half'],
        'lop-half' => ['icon' => 'LOP (0.5)', 'class' => 'status-lop-half'],
    ];

    $resolveStatusKey = function ($dateData) {
        if (!empty($dateData['override'])) {
            return $dateData['override'];
        }

        if (!empty($dateData['attendance']) && empty($dateData['leave'])) {
            $attendance = $dateData['attendance'][0];

            if (!empty($attendance->half_day) && $attendance->half_day == 'yes') {
                return 'halfday';
            }

            if (!empty($attendance->late) && $attendance->late == 'yes') {
                return 'late';
            }

            if (!empty($attendance->work_from_type)) {
                return 'wfh';
            }

            return 'present';
        }

        if (!empty($dateData['day_off'])) {
            return 'dayoff';
        }

        if (!empty($dateData['leave'])) {
            return 'leave';
        }

        if (!empty($dateData['holiday'])) {
            return 'holiday';
        }

        if ($dateData['is_future']) {
            return null;
        }

        return 'absent';
    };

    $monthLabel = \Carbon\Carbon::createFromDate($year, $month, 1)->translatedFormat('F Y');
    $monthNameOnly = \Carbon\Carbon::createFromDate($year, $month, 1)->translatedFormat('F');
    $dashboard = $monthDashboard ?: ['clAllocated' => 0, 'slAllocated' => 0, 'clUsed' => 0, 'slUsed' => 0, 'halfDays' => 0, 'clPending' => 0, 'slPending' => 0, 'carryForwardCL' => 0, 'remainingCL' => 0, 'remainingSL' => 0, 'lop' => 0];

    // Precomputed as plain variables (not inlined into @json() below) because
    // Blade's @json() directive naively splits its argument on every comma,
    // which mangles route() calls that take an array of params.
    $calendarUrl = route('attendances.calendar', [$calendarUser->id, $month, $year]);
    $saveDayStatusUrl = route('attendances.save_day_status');
    $csrfToken = csrf_token();
@endphp

<div class="attcal-scope">
    <div class="container">
        <!-- Header -->
        <div class="header-title">{{ $calendarUser->name }}</div>
        <div class="header-sub">{{ $calendarUser->name }}</div>
        <div class="header-date">{{ $monthLabel }}</div>

        <!-- Dashboard Title & Legend -->
        <div class="dashboard-row">
            <div class="dashboard-title"><span>📅</span> Monthly Dashboard — {{ $monthNameOnly }}</div>
            <div class="legend">
                <div class="legend-item"><span class="shape" style="color:#ecc94b;">★</span> Holiday</div>
                <div class="legend-item"><span class="shape square"></span> Day Off</div>
                <div class="legend-item"><span style="font-weight:bold; font-size:14px;">✓</span> Present</div>
                <div class="legend-item"><span style="color:#3182ce;">🏠</span> Work from home</div>
                <div class="legend-item"><span class="shape half-circle"></span> Half Day</div>
                <div class="legend-item"><span class="shape circle"></span> Late</div>
                <div class="legend-item"><span class="shape" style="color:#e53e3e; font-size:14px;">✗</span> Absent</div>
                <div class="legend-item"><span class="shape" style="color:#e53e3e; font-size:14px;">🚩</span> On Leave</div>
                <div class="legend-item" style="font-weight:bold; color:#3182ce;">CL</div>
                <div class="legend-item" style="font-weight:bold; color:#38a169;">SL</div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-row">
            <div class="card blue"><div class="card-icon">💼</div><div class="card-label">CL<br>ALLOCATED</div><div class="card-value" id="cl-allocated">{{ $dashboard['clAllocated'] }}</div></div>
            <div class="card green"><div class="card-icon">📅</div><div class="card-label">SL<br>ALLOCATED</div><div class="card-value" id="sl-allocated">{{ $dashboard['slAllocated'] }}</div></div>
            <div class="card yellow"><div class="card-icon">🟡</div><div class="card-label">CL USED</div><div class="card-value" id="cl-used">{{ $dashboard['clUsed'] }}</div></div>
            <div class="card red"><div class="card-icon">🔴</div><div class="card-label">SL USED</div><div class="card-value" id="sl-used">{{ $dashboard['slUsed'] }}</div></div>
            <div class="card teal"><div class="card-icon">◐</div><div class="card-label">HALF-DAYS</div><div class="card-value" id="half-days">{{ $dashboard['halfDays'] }}</div></div>
            <div class="card yellow"><div class="card-icon">⏳</div><div class="card-label">CL PENDING</div><div class="card-value" id="cl-pending">{{ $dashboard['clPending'] }}</div></div>
            <div class="card yellow"><div class="card-icon">⏳</div><div class="card-label">SL PENDING</div><div class="card-value" id="sl-pending">{{ $dashboard['slPending'] }}</div></div>
            <div class="card purple"><div class="card-icon">📅</div><div class="card-label">CARRY FWD<br>CL</div><div class="card-value" id="carry-fwd-cl">{{ $dashboard['carryForwardCL'] }}</div></div>
            <div class="card green"><div class="card-icon">🟢</div><div class="card-label">REMAINING<br>CL</div><div class="card-value" id="remaining-cl">{{ $dashboard['remainingCL'] }}</div></div>
            <div class="card blue"><div class="card-icon">👤</div><div class="card-label">REMAINING<br>SL</div><div class="card-value" id="remaining-sl">{{ $dashboard['remainingSL'] }}</div></div>
            <div class="card pink"><div class="card-icon">⚠️</div><div class="card-label">LOP</div><div class="card-value" id="lop-count">{{ $dashboard['lop'] }}</div></div>
        </div>

        <!-- Calendar Grid -->
        <div class="days-row">
            @foreach ($weekDayLabels as $label)
                <div>{{ $label }}</div>
            @endforeach
        </div>
        <div class="dates-grid">
            @for ($i = 0; $i < $leadingBlanks; $i++)
                <div class="date-box"></div>
            @endfor

            @foreach ($dateWiseData as $dateKey => $dateData)
                @php $statusKey = $resolveStatusKey($dateData); @endphp
                <div class="date-box" data-date="{{ $dateKey }}">
                    <div class="date-num">{{ $dateData['date']->day }}</div>
                    @if ($statusKey)
                        <div class="date-status {{ $statusMeta[$statusKey]['class'] }}">{{ $statusMeta[$statusKey]['icon'] }} <span class="edit-icon">✏️</span></div>
                    @else
                        <div class="date-status">-</div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <!-- ===== FLOATING ACTION BUTTON ===== -->
    <button class="floating-btn" id="bulkActionBtn">
        ✨ Merge Selected <span id="selectedCount"></span>
    </button>

    <!-- ===== SINGLE ASSIGN MODAL ===== -->
    <div class="modal-overlay" id="singleModal">
        <div class="modal-content">
            <div class="modal-header">Assign Status to Selected Date</div>
            <div class="modal-option" data-status="cl" data-icon="CL" data-class="status-cl"><span class="modal-option-icon">📄</span> CL</div>
            <div class="modal-option" data-status="sl" data-icon="SL" data-class="status-sl"><span class="modal-option-icon">📄</span> SL</div>
            <div class="modal-option" data-status="lop" data-icon="⚠️" data-class="status-lop"><span class="modal-option-icon">⚠️</span> LOP</div>
            <div class="modal-option" data-status="holiday" data-icon="★" data-class="status-holiday"><span class="modal-option-icon">★</span> Holiday</div>
            <div class="modal-option" data-status="present" data-icon="✓" data-class="status-present"><span class="modal-option-icon">✓</span> Present</div>
            <div class="modal-option" data-status="absent" data-icon="×" data-class="status-absent"><span class="modal-option-icon">×</span> Absent</div>
            <div class="modal-option" data-status="leave" data-icon="🚩" data-class="status-leave"><span class="modal-option-icon">🚩</span> On Leave</div>
            <div class="modal-option" data-status="wfh" data-icon="🏠" data-class="status-wfh"><span class="modal-option-icon">🏠</span> WFH</div>
            <div class="modal-option" data-status="halfday" data-icon="◐" data-class="status-halfday"><span class="modal-option-icon">◐</span> Half Day</div>
            <div class="modal-option" data-status="late" data-icon="🟡" data-class="status-late"><span class="modal-option-icon">🟡</span> Late</div>
            <div class="modal-option" data-status="dayoff" data-icon="⬛" data-class="status-dayoff"><span class="modal-option-icon">⬛</span> Day Off</div>
            <div class="modal-option" data-status="clear" data-icon="-" data-class=""><span class="modal-option-icon">🗑️</span> Clear Status</div>
            <button class="modal-cancel" id="singleCancelBtn">Cancel</button>
        </div>
    </div>

    <!-- ===== MERGE HALF DAYS MODAL ===== -->
    <div class="modal-overlay" id="mergeModal">
        <div class="modal-content" style="grid-template-columns: repeat(1, 1fr);">
            <div class="modal-header">Merge 2 Half Days into 1 Full Day</div>
            <div class="modal-header sub" style="font-weight: 400; margin-bottom: 15px;">
                You are merging <strong>2 half-days</strong> into a single day of leave.
                (0.5 + 0.5 = 1 Day Used)
            </div>

            <div class="modal-option" data-merge="cl" style="grid-column: 1;">
                <span class="modal-option-icon" style="color:#3182ce;">📄</span>
                Merge as Casual Leave (CL)
            </div>
            <div class="modal-option" data-merge="sl" style="grid-column: 1;">
                <span class="modal-option-icon" style="color:#38a169;">📄</span>
                Merge as Sick Leave (SL)
            </div>
            <div class="modal-option" data-merge="lop" style="grid-column: 1;">
                <span class="modal-option-icon" style="color:#fc8181;">⚠️</span>
                Merge as Loss of Pay (LOP)
            </div>
            <button class="modal-cancel" id="mergeCancelBtn">Cancel</button>
        </div>
    </div>

    <!-- ===== REVERT MERGE HALF DAYS MODAL ===== -->
    <div class="modal-overlay" id="revertModal">
        <div class="modal-content" style="grid-template-columns: repeat(1, 1fr);">
            <div class="modal-header">↩️ Revert Merged 0.5 Days</div>
            <div class="modal-header sub" style="font-weight: 400; margin-bottom: 15px;">
                Revert these two 0.5 days back to standard <strong>Half Days (◐)</strong>.
                This will automatically <strong>subtract 1</strong> from your used leave stats.
            </div>

            <div class="modal-option danger" data-revert="true" style="grid-column: 1;">
                <span class="modal-option-icon" style="color:#e53e3e;">↩️</span>
                Yes, Revert to Half Days
            </div>
            <button class="modal-cancel" id="revertCancelBtn">Cancel</button>
        </div>
    </div>
</div>

<style>
    /* ===== RESET & BASE (scoped to .attcal-scope so it can't leak into the rest of the app) ===== */
    .attcal-scope, .attcal-scope * { box-sizing: border-box; }
    .attcal-scope {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        background-color: #f4f7fa; color: #1a202c; padding: 30px; position: relative;
    }
    .attcal-scope .container { max-width: 1200px; margin: 0 auto; }

    /* ===== HEADER ===== */
    .attcal-scope .header-title { font-size: 28px; font-weight: 700; color: #2c5282; margin-bottom: 5px; }
    .attcal-scope .header-sub { font-size: 16px; font-weight: 600; color: #1a202c; margin-bottom: 2px; }
    .attcal-scope .header-date { font-size: 14px; color: #718096; }

    /* ===== DASHBOARD HEADER ROW ===== */
    .attcal-scope .dashboard-row {
        display: flex; justify-content: space-between; align-items: flex-start;
        margin-top: 25px; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;
    }
    .attcal-scope .dashboard-title {
        font-size: 18px; font-weight: 700; color: #1a202c;
        display: flex; align-items: center; gap: 10px;
    }
    .attcal-scope .dashboard-title span { color: #2b6cb0; }

    .attcal-scope .legend {
        display: flex; flex-wrap: wrap; gap: 12px; font-size: 12px; color: #4a5568; align-items: center;
    }
    .attcal-scope .legend-item { display: flex; align-items: center; gap: 4px; }
    .attcal-scope .legend-item .shape { display: inline-block; }
    .attcal-scope .shape.square { width: 10px; height: 10px; background: #e53e3e; border-radius: 1px; }
    .attcal-scope .shape.half-circle { width: 10px; height: 10px; border-radius: 50%; background: #ed8936; clip-path: inset(0 50% 0 0); }
    .attcal-scope .shape.circle { width: 10px; height: 10px; border-radius: 50%; background: #ecc94b; }

    /* ===== STATS CARDS ===== */
    .attcal-scope .stats-row { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 30px; }
    .attcal-scope .card {
        flex: 1 1 80px; min-width: 70px; background: #ffffff; border-radius: 6px;
        padding: 12px 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center;
    }
    .attcal-scope .card-icon { font-size: 20px; margin-bottom: 4px; }
    .attcal-scope .card-label { font-size: 9px; font-weight: 700; text-transform: uppercase; color: #718096; line-height: 1.2; letter-spacing: 0.5px; }
    .attcal-scope .card-value { font-size: 16px; font-weight: 700; color: #1a202c; margin-top: 6px; transition: 0.2s; }
    .attcal-scope .card.blue .card-icon { color: #3182ce; } .attcal-scope .card.green .card-icon { color: #38a169; }
    .attcal-scope .card.yellow .card-icon { color: #d69e2e; } .attcal-scope .card.red .card-icon { color: #e53e3e; }
    .attcal-scope .card.teal .card-icon { color: #319795; } .attcal-scope .card.purple .card-icon { color: #805ad5; }
    .attcal-scope .card.pink .card-icon { color: #fc8181; }

    /* ===== CALENDAR ===== */
    .attcal-scope .days-row {
        display: grid; grid-template-columns: repeat(7, 1fr);
        text-align: center; font-size: 14px; font-weight: 700; color: #1a202c;
        margin-bottom: 10px; padding: 0 5px;
    }
    .attcal-scope .dates-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px; }
    .attcal-scope .date-box {
        background: #ebf0f6; border: 1px solid #d2dce6; border-radius: 4px;
        padding: 15px 0; text-align: center; display: flex; flex-direction: column;
        align-items: center; justify-content: center; min-height: 60px;
        cursor: pointer; transition: all 0.2s ease; position: relative;
    }
    .attcal-scope .date-box:hover { background: #e2e8f0; }
    .attcal-scope .date-box.selected { border: 2px solid #2b6cb0; box-shadow: 0 0 0 1px #2b6cb0; }
    .attcal-scope .date-num { font-size: 16px; font-weight: 700; color: #1a202c; }

    /* ===== EDIT PENCIL ICON ===== */
    .attcal-scope .date-status {
        display: flex; align-items: center; justify-content: center; gap: 4px;
        font-size: 16px; margin-top: 4px; line-height: 1;
    }
    .attcal-scope .edit-icon {
        font-size: 11px; cursor: pointer; opacity: 0;
        transition: opacity 0.2s ease; color: #718096; border-radius: 4px; padding: 0 2px;
    }
    .attcal-scope .edit-icon:hover { background: #cbd5e0; color: #1a202c; }
    .attcal-scope .date-box:hover .edit-icon { opacity: 1; }

    /* Status Styles */
    .attcal-scope .status-cl { color: #3182ce; font-weight: 700; font-size: 14px; }
    .attcal-scope .status-sl { color: #38a169; font-weight: 700; font-size: 14px; }
    .attcal-scope .status-lop { color: #fc8181; font-weight: 700; font-size: 14px; }
    .attcal-scope .status-present { color: #38a169; font-weight: bold; }
    .attcal-scope .status-absent { color: #e53e3e; font-weight: bold; }
    .attcal-scope .status-leave { color: #e53e3e; }
    .attcal-scope .status-holiday { color: #ecc94b; }
    .attcal-scope .status-wfh { color: #3182ce; }
    .attcal-scope .status-halfday { color: #ed8936; }
    .attcal-scope .status-late { color: #ecc94b; }
    .attcal-scope .status-dayoff { color: #e53e3e; }

    /* Half Status Styles */
    .attcal-scope .status-cl-half { color: #3182ce; font-weight: 700; font-size: 12px; }
    .attcal-scope .status-sl-half { color: #38a169; font-weight: 700; font-size: 12px; }
    .attcal-scope .status-lop-half { color: #fc8181; font-weight: 700; font-size: 12px; }

    /* ===== FLOATING ACTION BUTTON ===== */
    .attcal-scope .floating-btn {
        position: fixed; bottom: 30px; right: 30px;
        background: #2b6cb0; color: white; border: none;
        padding: 12px 24px; border-radius: 30px; font-size: 16px; font-weight: 600;
        box-shadow: 0 4px 12px rgba(43, 108, 176, 0.3); cursor: pointer;
        display: none; align-items: center; gap: 8px; transition: all 0.2s ease;
        z-index: 1500;
    }
    .attcal-scope .floating-btn.revert-btn { background: #e53e3e; box-shadow: 0 4px 12px rgba(229, 62, 62, 0.3); }
    .attcal-scope .floating-btn.revert-btn:hover { background: #c53030; transform: scale(1.02); }
    .attcal-scope .floating-btn:hover { background: #2c5282; transform: scale(1.02); }
    .attcal-scope .floating-btn.visible { display: flex; }

    /* ===== MODALS ===== */
    .attcal-scope .modal-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0, 0, 0, 0.3); backdrop-filter: blur(2px);
        z-index: 1600; display: flex; align-items: center; justify-content: center;
        opacity: 0; pointer-events: none; transition: opacity 0.2s ease;
    }
    .attcal-scope .modal-overlay.active { opacity: 1; pointer-events: auto; }
    .attcal-scope .modal-content {
        background: #ffffff; padding: 25px; border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.2); max-width: 500px; width: 90%;
        display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px;
        transform: scale(0.95); transition: transform 0.2s ease;
    }
    .attcal-scope .modal-overlay.active .modal-content { transform: scale(1); }

    .attcal-scope .modal-header { grid-column: 1 / -1; text-align: center; font-weight: 700; margin-bottom: 10px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; font-size: 16px; color: #2d3748; }
    .attcal-scope .modal-header.sub { border-bottom: none; padding-bottom: 0; margin-bottom: 5px; font-size: 14px; color: #718096; }

    .attcal-scope .modal-option {
        background: #f7fafc; padding: 12px 8px; border-radius: 8px; border: 1px solid #e2e8f0;
        cursor: pointer; display: flex; flex-direction: column; align-items: center; gap: 4px;
        transition: all 0.1s ease; font-size: 12px; color: #4a5568;
    }
    .attcal-scope .modal-option:hover { background: #edf2f7; border-color: #a0aec0; transform: translateY(-1px); }
    .attcal-scope .modal-option-icon { font-size: 22px; }

    .attcal-scope .modal-cancel {
        grid-column: 1 / -1; margin-top: 10px; background: #edf2f7; border: none;
        padding: 10px; border-radius: 8px; cursor: pointer; color: #4a5568; font-weight: 600;
    }
    .attcal-scope .modal-cancel:hover { background: #e2e8f0; }

    /* Revert Modal Special Style */
    .attcal-scope .modal-option.danger { background: #fee2e2; border-color: #f87171; color: #991b1b; }
    .attcal-scope .modal-option.danger:hover { background: #fecaca; }

    /* Responsive */
    @media (max-width: 768px) {
        .attcal-scope .dashboard-row { flex-direction: column; align-items: flex-start; }
        .attcal-scope .card { min-width: 60px; flex-basis: 40px; } .attcal-scope .stats-row { gap: 6px; }
        .attcal-scope .modal-content { max-width: 300px; grid-template-columns: repeat(2, 1fr); }
    }
</style>

<script>
    (function () {
        const scope = document.querySelector('.attcal-scope');

        if (!scope) {
            return;
        }

        // The right-side slide-out panel that hosts this calendar is itself
        // `position:fixed` with a low z-index, which traps every fixed-position
        // descendant (the modals, the floating button) inside that stacking
        // context — other page elements with a higher z-index than the panel
        // can then sit visually on top and silently swallow clicks aimed at
        // them. Portal the fixed overlays out to <body> so their z-index is
        // compared against the whole page instead.
        const stalePortal = document.getElementById('attcalPortal');
        if (stalePortal) {
            stalePortal.remove();
        }

        const portal = document.createElement('div');
        portal.id = 'attcalPortal';
        portal.className = 'attcal-scope';
        document.body.appendChild(portal);

        ['#bulkActionBtn', '#singleModal', '#mergeModal', '#revertModal'].forEach(function (selector) {
            const el = scope.querySelector(selector);
            if (el) {
                portal.appendChild(el);
            }
        });

        const dateBoxes = scope.querySelectorAll('.date-box[data-date]');
        const singleModal = document.getElementById('singleModal');
        const mergeModal = document.getElementById('mergeModal');
        const revertModal = document.getElementById('revertModal');
        const singleCancelBtn = document.getElementById('singleCancelBtn');
        const mergeCancelBtn = document.getElementById('mergeCancelBtn');
        const revertCancelBtn = document.getElementById('revertCancelBtn');
        const bulkActionBtn = document.getElementById('bulkActionBtn');
        const selectedCountSpan = document.getElementById('selectedCount');
        const statusOptions = document.querySelectorAll('#singleModal .modal-option');
        const mergeOptions = document.querySelectorAll('#mergeModal .modal-option[data-merge]');
        const revertOptions = document.querySelectorAll('#revertModal .modal-option[data-revert]');

        // Stat Cards Elements
        const clUsedEl = scope.querySelector('#cl-used');
        const slUsedEl = scope.querySelector('#sl-used');
        const lopUsedEl = scope.querySelector('#lop-count');

        let selectedDateElements = [];

        // ===== PERSIST TO THE SERVER, THEN RELOAD THE PANEL =====
        // Re-fetching from the server (instead of hand-editing the DOM) guarantees
        // the calendar always shows exactly what's saved — no client/server drift.
        const CALENDAR_URL = @json($calendarUrl);
        const CSRF_TOKEN = @json($csrfToken);

        function saveDayStatus(dateStr, status) {
            return $.post({
                url: @json($saveDayStatusUrl),
                data: {
                    _token: CSRF_TOKEN,
                    user_id: {{ (int) $calendarUser->id }},
                    date: dateStr,
                    status: status || ''
                }
            });
        }

        function reloadPanel() {
            $.ajax({
                url: CALENDAR_URL,
                cache: false,
                success: function (response) {
                    $(RIGHT_MODAL_CONTENT).html(response);
                }
            });
        }

        function withSavingState(promise) {
            bulkActionBtn.disabled = true;

            promise
                .fail(function () {
                    alert('Could not save. Please try again.');
                })
                .always(function () {
                    reloadPanel();
                });
        }

        // ===== TOGGLE DATE SELECTION =====
        dateBoxes.forEach(box => {
            box.addEventListener('click', function(e) {
                if (e.target.closest('.edit-icon')) return;

                const index = selectedDateElements.indexOf(this);
                if (index > -1) {
                    this.classList.remove('selected');
                    selectedDateElements.splice(index, 1);
                } else {
                    this.classList.add('selected');
                    selectedDateElements.push(this);
                }
                updateBulkButton();
            });
        });

        // ===== EDIT ICON DIRECT CLICK =====
        scope.addEventListener('click', function(e) {
            if (e.target.closest('.edit-icon')) {
                e.stopPropagation();
                const targetBox = e.target.closest('.date-box');

                selectedDateElements.forEach(el => el.classList.remove('selected'));
                selectedDateElements = [];

                targetBox.classList.add('selected');
                selectedDateElements.push(targetBox);
                openSingleModal();
            }
        });

        // ===== UPDATE FLOATING BUTTON =====
        function updateBulkButton() {
            const count = selectedDateElements.length;
            if (count === 0) {
                bulkActionBtn.classList.remove('visible');
            } else if (count === 1) {
                bulkActionBtn.classList.add('visible');
                bulkActionBtn.className = 'floating-btn visible';
                selectedCountSpan.innerText = '(1) - Assign Status';
                bulkActionBtn.innerHTML = `📝 Assign Status <span id="selectedCount">(1)</span>`;
                bulkActionBtn.onclick = openSingleModal;
            } else if (count === 2) {
                const isHalfDay = selectedDateElements.every(el => {
                    const statusDiv = el.querySelector('.date-status');
                    return statusDiv && statusDiv.innerText.includes('◐');
                });

                const isHalfMerged = selectedDateElements.every(el => {
                    const statusDiv = el.querySelector('.date-status');
                    return statusDiv && (statusDiv.innerText.includes('CL (0.5)') || statusDiv.innerText.includes('SL (0.5)') || statusDiv.innerText.includes('LOP (0.5)'));
                });

                if (isHalfDay) {
                    bulkActionBtn.classList.add('visible');
                    bulkActionBtn.className = 'floating-btn visible';
                    selectedCountSpan.innerText = `(2) - Merge 0.5 Days`;
                    bulkActionBtn.innerHTML = `✨ Merge 0.5 + 0.5 <span id="selectedCount">(2)</span>`;
                    bulkActionBtn.onclick = openMergeModal;
                } else if (isHalfMerged) {
                    bulkActionBtn.classList.add('visible');
                    bulkActionBtn.className = 'floating-btn revert-btn visible';
                    selectedCountSpan.innerText = `(2) - Revert 0.5 Days`;
                    bulkActionBtn.innerHTML = `↩️ Revert 0.5 + 0.5 <span id="selectedCount">(2)</span>`;
                    bulkActionBtn.onclick = openRevertModal;
                } else {
                    bulkActionBtn.classList.add('visible');
                    bulkActionBtn.className = 'floating-btn visible';
                    selectedCountSpan.innerText = `(2) - Bulk Assign`;
                    bulkActionBtn.innerHTML = `📝 Bulk Assign <span id="selectedCount">(2)</span>`;
                    bulkActionBtn.onclick = openSingleModal;
                }
            } else {
                bulkActionBtn.classList.add('visible');
                bulkActionBtn.className = 'floating-btn visible';
                selectedCountSpan.innerText = `(${count}) - Bulk Assign`;
                bulkActionBtn.innerHTML = `📝 Bulk Assign <span id="selectedCount">(${count})</span>`;
                bulkActionBtn.onclick = openSingleModal;
            }
        }

        // ===== MODAL OPENERS =====
        function openSingleModal() {
            if (selectedDateElements.length === 0) return;
            singleModal.classList.add('active');
        }

        function openMergeModal() {
            if (selectedDateElements.length !== 2) return;
            mergeModal.classList.add('active');
        }

        function openRevertModal() {
            if (selectedDateElements.length !== 2) return;
            revertModal.classList.add('active');
        }

        // ===== CLOSE MODAL LOGIC =====
        function closeModal(modalEl) {
            modalEl.classList.remove('active');
            selectedDateElements.forEach(el => el.classList.remove('selected'));
            selectedDateElements = [];
            updateBulkButton();
        }

        singleCancelBtn.onclick = () => closeModal(singleModal);
        mergeCancelBtn.onclick = () => closeModal(mergeModal);
        revertCancelBtn.onclick = () => closeModal(revertModal);
        [singleModal, mergeModal, revertModal].forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) closeModal(modal);
            });
        });

        // ===== SINGLE / BULK ASSIGN LOGIC =====
        statusOptions.forEach(option => {
            option.addEventListener('click', function() {
                const status = this.dataset.status === 'clear' ? '' : this.dataset.status;
                const dates = selectedDateElements.map(el => el.dataset.date);

                closeModal(singleModal);
                withSavingState($.when(...dates.map(d => saveDayStatus(d, status))));
            });
        });

        // ===== MERGE HALF-DAYS LOGIC =====
        mergeOptions.forEach(option => {
            option.addEventListener('click', function() {
                const mergeType = this.dataset.merge;
                const dates = selectedDateElements.map(el => el.dataset.date);

                closeModal(mergeModal);
                withSavingState($.when(...dates.map(d => saveDayStatus(d, mergeType + '-half'))));
            });
        });

        // ===== REVERT MERGED HALF-DAYS LOGIC =====
        revertOptions.forEach(option => {
            option.addEventListener('click', function() {
                const dates = selectedDateElements.map(el => el.dataset.date);

                closeModal(revertModal);
                withSavingState($.when(...dates.map(d => saveDayStatus(d, 'halfday'))));
            });
        });
    })();
</script>
