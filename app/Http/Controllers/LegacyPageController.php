<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Serves the legacy PHP pages behind the application's login.
 *
 * These pages predate the framework and run as plain PHP with their own
 * database connection. They used to be reachable directly at /legacy/*.php,
 * which asked for no login at all: anyone with the URL could read salaries by
 * changing ?id=, or post a form to create a payslip. They are included from
 * here instead, so every request passes through authentication first, and the
 * pages holding salary data are limited to admins.
 *
 * The pages themselves are unchanged apart from their e() helper, which is now
 * declared only when the framework has not already defined it.
 */
class LegacyPageController extends AccountBaseController
{

    /**
     * The pages that may be served, and who may see them.
     *
     * 'admin' — salary or payroll data, the administrator role only.
     * 'user'  — any signed-in employee.
     *
     * A page may name a `permission` instead, which is what decides it. The
     * role test is blunt: Employee Termination opens for HR and the Admin,
     * whose own gate is edit_employees == 'all', and then the legacy page it
     * embeds refused them because they are not administrators — so the screen
     * rendered with "403 Permission Denied" inside its own frame. The pages
     * that are HR's work now ask for the permission HR holds; the pages
     * holding salary data keep the role test.
     */
    public const PAGES = [
        'reductions' => ['file' => 'Reductions.php', 'access' => 'admin', 'permission' => 'payroll_prepare', 'title' => 'Payslip Calculation'],
        'payslips' => ['file' => 'payslips.php', 'access' => 'admin', 'permission' => 'payroll_any', 'title' => 'Payslip'],
        'payslip_entry' => ['file' => 'payslip_entry.php', 'access' => 'admin', 'permission' => 'payroll_prepare', 'title' => 'Payslip Entry'],
        'payslip_inputs' => ['file' => 'payslip_inputs.php', 'access' => 'admin', 'permission' => 'payroll_settings', 'title' => 'Payslip Settings'],
        'offer_letter' => ['file' => 'offer-letter.php', 'access' => 'admin', 'permission' => 'edit_employees', 'title' => 'Offer Letter'],
        'termination' => ['file' => 'Termination.php', 'access' => 'admin', 'permission' => 'edit_employees', 'title' => 'Termination'],
        'termination_dashboard' => ['file' => 'TerminationDashboard.php', 'access' => 'admin', 'permission' => 'edit_employees', 'title' => 'Termination Dashboard'],
        'notice_period_settings' => ['file' => 'Notice_period_settings.php', 'access' => 'admin', 'permission' => 'edit_employees', 'title' => 'Notice Period Settings'],
        'probation_period_settings' => ['file' => 'Probation_period_settings.php', 'access' => 'admin', 'permission' => 'edit_employees', 'title' => 'Probation Period Settings'],
        'probation_period_request' => ['file' => 'probhition_period_request.php', 'access' => 'user', 'title' => 'Probation Period Requests'],
        'leave_management' => ['file' => 'leave_management.php', 'access' => 'user', 'title' => 'Leave Management'],
        'admin_leave' => ['file' => 'admin_leave.php', 'access' => 'user', 'title' => 'Leave & Attendance'],
        'attendance_dashboard' => ['file' => 'Attendance_Dashboard.php', 'access' => 'user', 'title' => 'Attendance Dashboard'],
        'identity' => ['file' => 'identity.php', 'access' => 'admin', 'permission' => 'edit_employees', 'title' => 'Identity Card'],
        'identity_card' => ['file' => 'identity_card.php', 'access' => 'admin', 'permission' => 'edit_employees', 'title' => 'Identity Card'],
        'joining_report' => ['file' => 'joining_report.php', 'access' => 'admin', 'permission' => 'edit_employees', 'title' => 'Joining Report'],
    ];

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Run one legacy page and return whatever it printed.
     */
    public function show(Request $request, string $page)
    {
        $definition = self::PAGES[$page] ?? abort(404);

        // A permission, where the page names one, decides on its own; the
        // administrator role passes everything regardless.
        if (isset($definition['permission'])) {
            // payroll_any: any payroll switch (App\Services\Access\PayrollAccess).
            $allowed = $definition['permission'] === 'payroll_any'
                ? \App\Services\Access\PayrollAccess::any()
                : (user()->permission($definition['permission']) === 'all' || in_array('admin', user_roles()));
            abort_403(!$allowed);
        } else {
            abort_403($definition['access'] === 'admin' && !in_array('admin', user_roles()));
        }

        $file = base_path($definition['file']);
        abort_unless(is_file($file), 404);

        $this->pageTitle = $definition['title'];

        // These pages read the request straight from PHP's superglobals. A web
        // server fills those in itself, so this changes nothing in normal use;
        // it keeps the page correct when the request was built by the framework
        // instead, as it is in tests and console runs.
        $_GET = $request->query->all();
        $_POST = $request->request->all();

        // Several of these pages decide whose data to show from the query
        // string. The signed-in user settles that instead: the company is
        // always their own, and only an admin may look at someone else.
        if (array_key_exists('company_id', $_GET)) {
            $_GET['company_id'] = company()?->id;
        }

        if (array_key_exists('user_id', $_GET) && !in_array('admin', user_roles())) {
            $_GET['user_id'] = user()->id;
        }

        $_REQUEST = array_merge($_GET, $_POST);
        $_SERVER['REQUEST_METHOD'] = $request->getMethod();

        // The page writes its own complete document, headers and all. Output is
        // captured so it can be returned as an ordinary response; a page that
        // calls exit() (the attendance lookup does, after printing JSON) still
        // reaches the browser, because PHP flushes the buffer on shutdown.
        // The pages link, post and redirect to themselves by file name
        // ("Termination.php?added=1"). Served from /account/legacy/{page},
        // that resolved to /account/legacy/Termination.php, which is not a
        // page, so picking a department or saving a form failed. Both the
        // printed links and the Location header are pointed back at the route.
        // Pages that exit() mid-way (every save does, after its redirect) are
        // covered too: shutdown functions run before PHP sends the headers,
        // and the buffer is flushed through the callback after that.
        register_shutdown_function(fn () => $this->fixLocationHeader());
        ob_start(fn ($html) => $this->fixLinks((string) $html));
        include $file;

        $html = ob_get_clean();
        $this->fixLocationHeader();

        return response($this->fixLinks($html));
    }

    /** Point "X.php" links at the route that serves X.php. */
    protected function fixLinks(string $html): string
    {
        foreach (self::PAGES as $key => $definition) {
            $html = preg_replace(
                '/(?<=["\'])(?:\.\/)?' . preg_quote($definition['file'], '/') . '(?=[?#"\'])/',
                route('legacy.page', $key),
                $html
            );
        }

        // The app's font, Open Sans, in these pages too (they write their own <head>).
        if (!str_contains($html, 'css/open-sans.css')) {
            $font = '<link rel="stylesheet" href="' . asset('css/open-sans.css') . '?v=' . @filemtime(public_path('css/open-sans.css')) . '">';

            // The Epic HR look on top, unless the person uses the dark theme.
            if (!user()?->dark_theme) {
                foreach (['fonts', 'legacy'] as $sheet) {
                    $font .= '<link rel="stylesheet" href="' . asset('css/epic/' . $sheet . '.css') . '?v=' . @filemtime(public_path('css/epic/' . $sheet . '.css')) . '">';
                }

                if ($colors = \App\Services\Theme\EpicColors::css()) {
                    $font .= '<style id="epic-colors">' . $colors . '</style>';
                }
            }
            $html = preg_replace('#</head>#i', $font . '$0', $html, 1);
        }

        // Their forms predate CSRF protection; a POST without the token was
        // refused and bounced to the login page (the dashboard, once signed in).
        $token = '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';

        return preg_replace('/<form\b[^>]*\bmethod\s*=\s*["\']?post\b[^>]*>/i', '$0' . $token, $html);
    }

    protected function fixLocationHeader(): void
    {
        if (headers_sent()) {
            return;
        }

        foreach (headers_list() as $header) {
            if (stripos($header, 'Location:') !== 0) {
                continue;
            }

            $location = ltrim(trim(substr($header, 9)), './');

            foreach (self::PAGES as $key => $definition) {
                if (str_starts_with($location, $definition['file'])) {
                    header('Location: ' . route('legacy.page', $key) . substr($location, strlen($definition['file'])), true);

                    return;
                }
            }
        }
    }

}
