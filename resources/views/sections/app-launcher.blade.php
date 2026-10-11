@php
    // Every "show" condition below is copied verbatim from resources/views/sections/menu.blade.php
    // so this launcher never shows a tile the sidebar itself wouldn't show.
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
    $showAppreciation = $showHRGroup && ((isset($sidebarUserPermissions['view_appreciation']) && $sidebarUserPermissions['view_appreciation'] != 5)
        || (isset($sidebarUserPermissions['view_appreciation']) && $sidebarUserPermissions['view_appreciation'] == 5 && isset($sidebarUserPermissions['manage_award']) && $sidebarUserPermissions['manage_award'] == 4));
    $appreciationRoute = (isset($sidebarUserPermissions['view_appreciation']) && $sidebarUserPermissions['view_appreciation'] == 5) ? 'awards.index' : 'appreciations.index';

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
    $showTaskReport = $showReportsGroup && $sidebarUserPermissions['view_task_report'] == 4 && in_array('tasks', user_modules());
    $showTimeLogReport = $showReportsGroup && $sidebarUserPermissions['view_time_log_report'] == 4 && in_array('timelogs', user_modules());
    $showWeeklyTimesheet = $showTimeLogReport;
    $showFinanceReport = $showReportsGroup && $sidebarUserPermissions['view_finance_report'] != 5 && $sidebarUserPermissions['view_finance_report'] != 'none' && in_array('payments', user_modules());
    $showIncomeVsExpense = $showReportsGroup && $sidebarUserPermissions['view_income_expense_report'] == 4 && in_array('expenses', user_modules());
    $showLeaveReport = $showReportsGroup && $sidebarUserPermissions['view_leave_report'] != 5 && $sidebarUserPermissions['view_leave_report'] != 'none' && in_array('leaves', user_modules());
    $showAttendanceReport = $showReportsGroup && $sidebarUserPermissions['view_attendance_report'] == 4 && in_array('attendance', user_modules());
    $showExpenseReport = $showReportsGroup && isset($sidebarUserPermissions['view_expense_report']) && $sidebarUserPermissions['view_expense_report'] == 4 && in_array('expenses', user_modules());
    $showDealReport = $showReportsGroup && isset($sidebarUserPermissions['view_lead_report']) && $sidebarUserPermissions['view_lead_report'] == 4 && in_array('leads', user_modules());
    $showSalesReport = $showReportsGroup && isset($sidebarUserPermissions['view_sales_report']) && $sidebarUserPermissions['view_sales_report'] == 4 && in_array('invoices', user_modules());

    // Category headers have no single destination in the sidebar (they're accordion
    // toggles) — land on the first sub-page the user actually has access to.
    $firstOf = function (array $pairs) {
        foreach ($pairs as [$show, $routeName]) {
            if ($show) {
                return $routeName;
            }
        }
        return null;
    };

    $leadsRoute = $firstOf([[$showLeadContact, 'lead-contact.index'], [$showDeals, 'deals.index']]);
    $hrRoute = $firstOf([[$showEmployees, 'employees.index'], [$showLeaves, 'leaves.index'], [$showIdentityCard, 'identity.index'], [$showPayslips, 'payslips.index']]);
    $workRoute = $firstOf([[$showContracts, 'contracts.index'], [$showProjects, 'projects.index'], [$showTasks, 'tasks.index'], [$showTimesheet, 'timelogs.index']]);
    $financeRoute = $firstOf([[$showProposal, 'proposals.index'], [$showEstimates, 'estimates.index'], [$showInvoices, 'invoices.index'], [$showPayments, 'payments.index'], [$showExpenses, 'expenses.index'], [$showBankAccount, 'bankaccounts.index']]);
    $reportsRoute = $firstOf([[$showTaskReport, 'task-report.index'], [$showTimeLogReport, 'time-log-report.index'], [$showFinanceReport, 'finance-report.index'], [$showIncomeVsExpense, 'income-expense-report.index'], [$showLeaveReport, 'leave-report.leave_quota'], [$showAttendanceReport, 'attendance-report.index'], [$showExpenseReport, 'expense-report.index'], [$showDealReport, 'lead-report.index'], [$showSalesReport, 'sales-report.index']]);

    $palette = ['#0d9488', '#7c3aed', '#dc2626', '#16a34a', '#2563eb', '#d97706', '#db2777', '#0891b2', '#65a30d', '#9333ea', '#ea580c', '#0f766e', '#4f46e5', '#be123c'];

    $tiles = [
        ['show' => $showDashboard, 'label' => __('app.menu.dashboard'), 'slug' => 'dashboard', 'route' => 'dashboard', 'icon' => 'home'],
        ['show' => $showMyCalendar, 'label' => __('app.menu.myCalendar'), 'slug' => 'my-calendar', 'route' => 'my-calendar.index', 'icon' => 'calendar-alt'],
        ['show' => $showLeadsGroup, 'label' => __('app.menu.lead'), 'slug' => 'leads', 'route' => $leadsRoute, 'icon' => 'bullseye'],
        ['show' => $showClients, 'label' => __('app.menu.clients'), 'slug' => 'clients', 'route' => 'clients.index', 'icon' => 'building'],
        ['show' => $showHRGroup, 'label' => __('app.menu.hr'), 'slug' => 'hr', 'route' => $hrRoute, 'icon' => 'users'],
        ['show' => $showEmployees, 'label' => __('app.menu.employees'), 'slug' => 'employees', 'route' => 'employees.index', 'icon' => 'user-tie'],
        ['show' => $showLeaves, 'label' => __('app.menu.leaves'), 'slug' => 'leaves', 'route' => 'leaves.index', 'icon' => 'plane-departure'],
        ['show' => $showLeaveManagement, 'label' => __('app.menu.leaveManagement'), 'slug' => 'leave-mgmt', 'route' => 'leave-management.index', 'icon' => 'calendar-check'],
        ['show' => $showIdentityCard, 'label' => __('app.menu.identityCard'), 'slug' => 'identity', 'route' => 'identity.index', 'icon' => 'id-card'],
        ['show' => $showPayslips, 'label' => __('app.menu.payslips'), 'slug' => 'payslips', 'route' => 'payslips.index', 'icon' => 'file-invoice-dollar'],
        ['show' => $showShiftRoster, 'label' => __('app.menu.shiftRoster'), 'slug' => 'shift-roster', 'route' => 'shifts.index', 'icon' => 'business-time'],
        ['show' => $showAttendance, 'label' => __('app.menu.attendance'), 'slug' => 'attendance', 'route' => 'attendances.index', 'icon' => 'clipboard-check'],
        ['show' => $showHoliday, 'label' => __('app.menu.holiday'), 'slug' => 'holiday', 'route' => 'holidays.index', 'icon' => 'umbrella-beach'],
        ['show' => $showDesignation, 'label' => __('app.menu.designation'), 'slug' => 'designation', 'route' => 'designations.index', 'icon' => 'user-tag'],
        ['show' => $showDepartment, 'label' => __('app.menu.department'), 'slug' => 'department', 'route' => 'departments.index', 'icon' => 'sitemap'],
        ['show' => $showWorkGroup, 'label' => __('app.menu.work'), 'slug' => 'work', 'route' => $workRoute, 'icon' => 'briefcase'],
        ['show' => $showFinanceGroup, 'label' => __('app.menu.finance'), 'slug' => 'finance', 'route' => $financeRoute, 'icon' => 'coins'],
        ['show' => $showProducts, 'label' => __('app.menu.products'), 'slug' => 'products', 'route' => 'products.index', 'icon' => 'box-open'],
        ['show' => $showOrders, 'label' => __('app.menu.orders'), 'slug' => 'orders', 'route' => 'orders.index', 'icon' => 'shopping-cart'],
        ['show' => $showTickets, 'label' => __('app.menu.tickets'), 'slug' => 'tickets', 'route' => 'tickets.index', 'icon' => 'headset'],
        ['show' => $showEvents, 'label' => __('app.menu.events'), 'slug' => 'events', 'route' => 'events.index', 'icon' => 'calendar-day'],
        ['show' => $showMessages, 'label' => __('app.menu.messages'), 'slug' => 'messages', 'route' => 'messages.index', 'icon' => 'comment-dots'],
        ['show' => $showNoticeBoard, 'label' => __('app.menu.noticeBoard'), 'slug' => 'notice-board', 'route' => 'notices.index', 'icon' => 'thumbtack'],
        ['show' => $showKnowledgeBase, 'label' => __('app.menu.knowledgebase'), 'slug' => 'knowledgebase', 'route' => 'knowledgebase.index', 'icon' => 'book'],
        ['show' => $showReportsGroup, 'label' => __('app.menu.reports'), 'slug' => 'reports', 'route' => $reportsRoute, 'icon' => 'chart-pie'],
        ['show' => true, 'slug' => 'settings', 'label' => __('app.menu.settings'), 'route' => ($sidebarUserPermissions['manage_company_setting'] == 4 ? 'company-settings.index' : 'profile-settings.index'), 'icon' => 'cog'],
    ];

    $visibleTiles = [];
    $colorIndex = 0;

    foreach ($tiles as $tile) {
        if (!$tile['show'] || empty($tile['route']) || !\Illuminate\Support\Facades\Route::has($tile['route'])) {
            continue;
        }

        $visibleTiles[] = [
            'label' => $tile['label'],
            'url' => route($tile['route']),
            'icon' => $tile['icon'],
            'color' => $palette[$colorIndex % count($palette)],
        ];
        $colorIndex++;
    }

    // ---- tiles from the database -----------------------------------------
    // App Launcher Settings decides a tile's name, icon, colour, order and
    // whether it appears at all. It never decides who may see it: that is
    // still the $show flags above, looked up by slug. A tile with a slug the
    // flags do not know falls back to whether the person can reach that menu
    // at all.
    //
    // With no rows, the hand-written list above stands — so an empty table
    // leaves the launcher exactly as it was.
    $launcherRows = \App\Models\AppLauncher::forCompany(company()?->id)
        ->where('is_active', 1)
        ->orderBy('sort_order')
        ->orderBy('id')
        ->get();

    if ($launcherRows->isNotEmpty()) {
        $showBySlug = [];

        foreach ($tiles as $tile) {
            if (isset($tile['slug'])) {
                $showBySlug[$tile['slug']] = (bool) $tile['show'];
            }
        }

        $visibleTiles = [];
        $colorIndex = 0;

        foreach ($launcherRows as $row) {
            $allowed = array_key_exists($row->slug, $showBySlug)
                ? $showBySlug[$row->slug]
                : user()->hasMenuAccess($row->slug);

            if (!$allowed) {
                continue;
            }

            $url = $row->url;

            if (!$url && $row->route && \Illuminate\Support\Facades\Route::has($row->route)) {
                $url = route($row->route);
            }

            if (!$url) {
                continue;
            }

            $icon = trim((string) $row->icon) ?: 'fa fa-th';

            if (!preg_match('/^(fa|fas|far|fab|fal|fad|bi)\s/', $icon)) {
                $icon = 'fa fa-' . ltrim($icon, 'fa-');
            }

            $visibleTiles[] = [
                'label' => $row->name,
                'url' => $url,
                'target' => $row->target ?: '_self',
                'icon' => $icon,
                'color' => $row->color ?: $palette[$colorIndex % count($palette)],
            ];
            $colorIndex++;
        }
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
                <a href="{{ $tile['url'] }}" class="app-launcher-tile" data-label="{{ strtolower($tile['label']) }}">
                    <span class="app-launcher-icon" style="background-color: {{ $tile['color'] }}">
                        <i class="fa fa-{{ $tile['icon'] }}"></i>
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

        /*
         * The magnifier sits inside the field as a flex item rather than being
         * positioned over it. Absolute positioning put it above the box and to
         * the left — something in the theme's stylesheet was winning on
         * `position`, and a layout that does not depend on `position` at all
         * cannot lose that argument. The icon is on the left, where the rest of
         * the application puts it.
         */
        .app-launcher-search {
            display: flex;
            align-items: center;
            gap: 8px;
            max-width: 420px;
            margin: 0 auto 20px;
            padding: 0 14px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #fff;
        }

        .app-launcher-search i {
            flex: 0 0 auto;
            color: #94a3b8;
            font-size: 13px;
            line-height: 1;
        }

        .app-launcher-search input {
            flex: 1 1 auto;
            min-width: 0;
            border: 0;
            background: transparent;
            padding: 9px 0;
            font-size: 13px;
        }

        .app-launcher-search input:focus {
            outline: none;
            box-shadow: none;
        }

        .app-launcher-search:focus-within {
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

            if (!searchInput) {
                return;
            }

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
