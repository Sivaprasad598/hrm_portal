@php
    /**
     * HYBRID APP LAUNCHER
     * - Tile metadata (name, icon, color, order, active) comes from `app_launchers` table
     * - Visibility still uses the detailed permission checks below (per slug)
     * - New tiles added via Settings can use `hasMenuAccess()` fallback
     */

    // =========================================================
    // 1) BUILD THE SHOW-MAP FROM YOUR EXISTING PERMISSION LOGIC
    // =========================================================

    $showDashboard = in_array('admin', user_roles())
        || $sidebarUserPermissions['view_overview_dashboard'] == 4
        || $sidebarUserPermissions['view_project_dashboard'] == 4
        || $sidebarUserPermissions['view_client_dashboard'] == 4
        || $sidebarUserPermissions['view_hr_dashboard'] == 4
        || $sidebarUserPermissions['view_ticket_dashboard'] == 4
        || $sidebarUserPermissions['view_finance_dashboard'] == 4;

    $showMyCalendar = in_array('tasks', user_modules()) || in_array('events', user_modules()) || in_array('holidays', user_modules())
        || in_array('tickets', user_modules()) || in_array('leaves', user_modules());

    $showLeadsGroup = !in_array('client', user_roles()) && in_array('leads', user_modules())
        && (($sidebarUserPermissions['view_lead'] != 5 && $sidebarUserPermissions['view_lead'] != 'none') || ($sidebarUserPermissions['view_deals'] != 5 && $sidebarUserPermissions['view_deals'] != 'none'));
    $showLeadContact = $showLeadsGroup && $sidebarUserPermissions['view_lead'] != 5 && $sidebarUserPermissions['view_lead'] != 'none';
    $showDeals = $showLeadsGroup && $sidebarUserPermissions['view_deals'] != 5 && $sidebarUserPermissions['view_deals'] != 'none';

    $showClients = !in_array('client', user_roles()) && in_array('clients', user_modules())
        && $sidebarUserPermissions['view_clients'] != 5 && $sidebarUserPermissions['view_clients'] != 'none';

    $showHRGroup = !in_array('client', user_roles())
        && (in_array('employees', user_modules()) || in_array('leaves', user_modules()) || in_array('attendance', user_modules()) || in_array('holidays', user_modules()))
        && ($sidebarUserPermissions['view_employees'] != 5 || $sidebarUserPermissions['view_leave'] != 5 || $sidebarUserPermissions['view_attendance'] != 5 || $sidebarUserPermissions['view_holiday'] != 5)
        && ($sidebarUserPermissions['view_employees'] != 'none' || $sidebarUserPermissions['view_leave'] != 'none' || $sidebarUserPermissions['view_attendance'] != 'none' || $sidebarUserPermissions['view_holiday'] != 'none' || $sidebarUserPermissions['view_shift_roster'] != 'none');
    $showEmployees = $showHRGroup && in_array('employees', user_modules()) && $sidebarUserPermissions['view_employees'] != 5 && $sidebarUserPermissions['view_employees'] != 'none' && isset($sidebarUserPermissions['view_employee_menu']) && $sidebarUserPermissions['view_employee_menu'] == 4;
    $showLeaves = $showHRGroup && in_array('leaves', user_modules()) && $sidebarUserPermissions['view_leave'] != 5 && $sidebarUserPermissions['view_leave'] != 'none';
    $showLeaveManagement = $showLeaves;
    $showIdentityCard = $showHRGroup && in_array('admin', user_roles());
    $showPayslips = $showHRGroup && (in_array('employee', user_roles()) || in_array('admin', user_roles()));
    $showShiftRoster = $showHRGroup && in_array('attendance', user_modules()) && isset($sidebarUserPermissions['view_shift_roster']) && $sidebarUserPermissions['view_shift_roster'] != 5 && $sidebarUserPermissions['view_shift_roster'] != 'none';
    $showAttendance = $showHRGroup && in_array('attendance', user_modules()) && $sidebarUserPermissions['view_attendance'] != 5 && $sidebarUserPermissions['view_attendance'] != 'none';
    $showHoliday = $showHRGroup && in_array('holidays', user_modules()) && $sidebarUserPermissions['view_holiday'] != 5 && $sidebarUserPermissions['view_holiday'] != 'none';
    $showDesignation = $showHRGroup && isset($sidebarUserPermissions['view_designation']) && $sidebarUserPermissions['view_designation'] == 4;
    $showDepartment = $showHRGroup && isset($sidebarUserPermissions['view_department']) && $sidebarUserPermissions['view_department'] == 4;

    $showWorkGroup = (in_array('contracts', user_modules()) || in_array('projects', user_modules()) || in_array('tasks', user_modules()) || in_array('timelogs', user_modules()))
        && ($sidebarUserPermissions['view_contract'] != 5 || $sidebarUserPermissions['view_projects'] != 5 || $sidebarUserPermissions['view_tasks'] != 5 || $sidebarUserPermissions['view_timelogs'] != 5)
        && ($sidebarUserPermissions['view_contract'] != 'none' || $sidebarUserPermissions['view_projects'] != 'none' || $sidebarUserPermissions['view_tasks'] != 'none' || $sidebarUserPermissions['view_timelogs'] != 'none');
    $showContracts = $showWorkGroup && in_array('contracts', user_modules()) && $sidebarUserPermissions['view_contract'] != 5 && $sidebarUserPermissions['view_contract'] != 'none';
    $showProjects = $showWorkGroup && in_array('projects', user_modules()) && $sidebarUserPermissions['view_projects'] != 5 && $sidebarUserPermissions['view_projects'] != 'none';
    $showTasks = $showWorkGroup && in_array('tasks', user_modules()) && $sidebarUserPermissions['view_tasks'] != 5 && $sidebarUserPermissions['view_tasks'] != 'none';
    $showTimesheet = $showWorkGroup && in_array('timelogs', user_modules()) && $sidebarUserPermissions['view_timelogs'] != 5 && $sidebarUserPermissions['view_timelogs'] != 'none';

    $showFinanceGroup = (in_array('estimates', user_modules()) || in_array('invoices', user_modules()) || in_array('payments', user_modules()) || in_array('expenses', user_modules()) || in_array('bankaccount', user_modules()))
        && ($sidebarUserPermissions['view_estimates'] != 5 || $sidebarUserPermissions['view_invoices'] != 5 || $sidebarUserPermissions['view_payments'] != 5 || $sidebarUserPermissions['view_expenses'] != 5 || $sidebarUserPermissions['view_lead_proposals'] != 5 || $sidebarUserPermissions['view_bankaccount'] != 5)
        && ($sidebarUserPermissions['view_estimates'] != 'none' || $sidebarUserPermissions['view_invoices'] != 'none' || $sidebarUserPermissions['view_payments'] != 'none' || $sidebarUserPermissions['view_expenses'] != 'none' || $sidebarUserPermissions['view_lead_proposals'] != 'none' || $sidebarUserPermissions['view_bankaccount'] != 'none');
    $showProposal = $showFinanceGroup && in_array('leads', user_modules()) && $sidebarUserPermissions['view_lead_proposals'] != 5 && $sidebarUserPermissions['view_lead_proposals'] != 'none';
    $showEstimates = $showFinanceGroup && in_array('estimates', user_modules()) && $sidebarUserPermissions['view_estimates'] != 5 && $sidebarUserPermissions['view_estimates'] != 'none';
    $showInvoices = $showFinanceGroup && in_array('invoices', user_modules()) && $sidebarUserPermissions['view_invoices'] != 5 && $sidebarUserPermissions['view_invoices'] != 'none';
    $showPayments = $showFinanceGroup && in_array('payments', user_modules()) && $sidebarUserPermissions['view_payments'] != 5 && $sidebarUserPermissions['view_payments'] != 'none';
    $showCreditNote = $showInvoices;
    $showExpenses = $showFinanceGroup && in_array('expenses', user_modules()) && $sidebarUserPermissions['view_expenses'] != 5 && $sidebarUserPermissions['view_expenses'] != 'none';
    $showBankAccount = $showFinanceGroup && in_array('bankaccount', user_modules()) && $sidebarUserPermissions['view_bankaccount'] != 5 && $sidebarUserPermissions['view_bankaccount'] != 'none';

    $showProducts = !in_array('purchase', user_modules()) && in_array('products', user_modules()) && $sidebarUserPermissions['view_product'] != 5 && $sidebarUserPermissions['view_product'] != 'none';
    $showOrders = in_array('orders', user_modules()) && $sidebarUserPermissions['view_order'] != 5 && $sidebarUserPermissions['view_order'] != 'none';
    $showTickets = in_array('tickets', user_modules()) && $sidebarUserPermissions['view_tickets'] != 5 && $sidebarUserPermissions['view_tickets'] != 'none';
    $showEvents = in_array('events', user_modules()) && $sidebarUserPermissions['view_events'] != 5 && $sidebarUserPermissions['view_events'] != 'none';
    $showMessages = in_array('messages', user_modules()) && !in_array('groupmessage', user_modules())
        && ((in_array('client', user_roles()) && (message_setting()->allow_client_admin == 'yes' || message_setting()->allow_client_employee == 'yes')) || in_array('employee', user_roles()));
    $showNoticeBoard = in_array('notices', user_modules()) && $sidebarUserPermissions['view_notice'] != 5 && $sidebarUserPermissions['view_notice'] != 'none';
    $showKnowledgeBase = in_array('knowledgebase', user_modules()) && isset($sidebarUserPermissions['view_knowledgebase']) && $sidebarUserPermissions['view_knowledgebase'] != 5;

    $showReportsGroup = in_array('reports', user_modules())
        && ($sidebarUserPermissions['view_task_report'] == 4 || $sidebarUserPermissions['view_time_log_report'] == 4 || (isset($sidebarUserPermissions['view_expense_report']) && $sidebarUserPermissions['view_expense_report'] == 4) || $sidebarUserPermissions['view_finance_report'] != 5 || $sidebarUserPermissions['view_income_expense_report'] == 4 || $sidebarUserPermissions['view_leave_report'] != 5 || $sidebarUserPermissions['view_attendance_report'] == 4 || $sidebarUserPermissions['view_lead_report'] == 4 || $sidebarUserPermissions['view_sales_report'] == 4)
        && ($sidebarUserPermissions['view_task_report'] != 'none' || $sidebarUserPermissions['view_time_log_report'] != 'none' || $sidebarUserPermissions['view_finance_report'] != 'none' || $sidebarUserPermissions['view_income_expense_report'] != 'none' || $sidebarUserPermissions['view_leave_report'] != 'none' || $sidebarUserPermissions['view_attendance_report'] != 'none' || $sidebarUserPermissions['view_lead_report'] != 'none' || $sidebarUserPermissions['view_sales_report'] != 'none' || (isset($sidebarUserPermissions['view_expense_report']) && $sidebarUserPermissions['view_expense_report'] != 'none'));

    // =========================================================
    // 2) SHOW-MAP: slug → boolean
    //    Used to filter DB-driven launchers.
    // =========================================================
    $showMap = [
        'dashboard'       => $showDashboard,
        'my-calendar'     => $showMyCalendar,
        'leads'           => $showLeadsGroup,
        'clients'         => $showClients,
        'hr'              => $showHRGroup,
        'employees'       => $showEmployees,
        'leaves'          => $showLeaves,
        'leave-mgmt'      => $showLeaveManagement,
        'identity'        => $showIdentityCard,
        'payslips'        => $showPayslips,
        'shift-roster'    => $showShiftRoster,
        'attendance'      => $showAttendance,
        'holiday'         => $showHoliday,
        'designation'     => $showDesignation,
        'department'      => $showDepartment,
        'work'            => $showWorkGroup,
        'finance'         => $showFinanceGroup,
        'products'        => $showProducts,
        'orders'          => $showOrders,
        'tickets'         => $showTickets,
        'events'          => $showEvents,
        'messages'        => $showMessages,
        'notice-board'    => $showNoticeBoard,
        'knowledgebase'   => $showKnowledgeBase,
        'reports'         => $showReportsGroup,
        'settings'        => true,
    ];

    // =========================================================
    // 3) LOAD LAUNCHERS FROM DATABASE
    // =========================================================
    $launchers = cache()->remember('app_launchers_all', now()->addMinutes(30), function () {
        return \App\Models\AppLauncher::orderBy('sort_order')->get();
    });

    $visibleTiles = [];

    foreach ($launchers as $l) {
        // Must be active
        if (!$l->is_active) continue;

        // Check permission
        // 1) If it's a known slug, use the detailed PHP check
        // 2) Otherwise, fall back to hasMenuAccess()
        if (array_key_exists($l->slug, $showMap)) {
            $allowed = $showMap[$l->slug];
        } else {
            $allowed = user()->hasMenuAccess($l->slug);
        }

        if (!$allowed) continue;

        // Resolve URL
        $routeExists = $l->route && \Illuminate\Support\Facades\Route::has($l->route);
        $url = $l->url ?: ($routeExists ? route($l->route) : null);
        if (!$url) continue;

        // Handle icon: if it doesn't start with "fa", assume it's a bare FA name
        $iconClass = trim((string) $l->icon) ?: 'fa fa-th';
        if (strpos($iconClass, 'fa') !== 0) {
            $iconClass = 'fa fa-' . $iconClass;
        }

        // The app loads Font Awesome 5: map FA6 style prefixes (fa-solid, fa-regular, fa-brands) to FA5 ones
        $iconClass = str_replace(
            ['fa-solid', 'fa-regular', 'fa-brands'],
            ['fas', 'far', 'fab'],
            $iconClass
        );

        $visibleTiles[] = [
            'label'  => $l->name,
            'url'    => $url,
            'target' => $l->target ?: '_self',
            'icon'   => $iconClass,
            'color'  => $l->color ?: '#4f46e5',
        ];
    }
@endphp

@if (count($visibleTiles))
    <div class="app-launcher-box mb-4">
        <div class="app-launcher-search">
            <i class="fa fa-search"></i>
            <input type="text" id="appLauncherSearch" placeholder="@lang('app.startTyping')" autocomplete="off">
        </div>

        <div class="app-launcher-grid" id="appLauncherGrid">
            @foreach ($visibleTiles as $tile)
                <a href="{{ $tile['url'] }}"
                   target="{{ $tile['target'] }}"
                   class="app-launcher-tile"
                   data-label="{{ strtolower($tile['label']) }}">
                    <span class="app-launcher-icon" style="background-color: {{ $tile['color'] }}">
                        <i class="{{ $tile['icon'] }}"></i>
                    </span>
                    <span class="app-launcher-label">{{ $tile['label'] }}</span>
                </a>
            @endforeach
        </div>
    </div>

    <style>
        .app-launcher-box {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            padding: 20px;
        }
        .app-launcher-search {
            position: relative;
            max-width: 420px;
            margin: 0 auto 20px;
        }
        .app-launcher-search i {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 13px;
        }
        .app-launcher-search input {
            width: 100%;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 9px 36px 9px 14px;
            font-size: 13px;
        }
        .app-launcher-search input:focus {
            outline: none;
            border-color: #2563eb;
        }
        .app-launcher-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
            gap: 18px 10px;
            justify-items: center;
        }
        .app-launcher-tile {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            width: 90px;
            text-decoration: none;
        }
        .app-launcher-tile:hover .app-launcher-icon {
            transform: translateY(-3px);
            box-shadow: 0 6px 14px rgba(0, 0, 0, 0.18);
        }
        .app-launcher-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 20px;
            transition: all 0.2s ease;
        }
        .app-launcher-label {
            margin-top: 6px;
            font-size: 12px;
            font-weight: 500;
            color: #334155;
            line-height: 1.3;
        }
        .app-launcher-tile.d-none {
            display: none !important;
        }
    </style>

    <script>
        (function () {
            var searchInput = document.getElementById('appLauncherSearch');
            if (!searchInput) return;

            searchInput.addEventListener('input', function () {
                var query = searchInput.value.trim().toLowerCase();
                document.querySelectorAll('#appLauncherGrid .app-launcher-tile').forEach(function (tile) {
                    var matches = !query || tile.getAttribute('data-label').indexOf(query) !== -1;
                    tile.classList.toggle('d-none', !matches);
                });
            });
        })();
    </script>
@endif
