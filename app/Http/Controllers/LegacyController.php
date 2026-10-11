<?php

namespace App\Http\Controllers;

use App\Helper\Reply;

class LegacyController extends AccountBaseController
{
    public function identity()
    {
        $this->pageTitle = 'app.menu.identityCard';

        // Only allow admin users to view the identity card page
        if (!in_array('admin', user_roles())) {
            abort(403);
        }

        return view('identity.index', $this->data);
    }

    public function leaveManagement()
    {
        $this->pageTitle = 'app.menu.leaveManagement';

        $viewPermission = user()->permission('view_leave');
        abort_403(!in_array('leaves', user_modules()) || !in_array($viewPermission, ['all', 'added', 'owned', 'both']));

        return view('leaves.management', $this->data);
    }

    public function offerLetter()
    {
        $this->pageTitle = 'app.menu.offerLetter';

        // An offer letter is a joining document, so it takes the permission the
        // other employee documents take. It was the administrator role alone,
        // which meant the picker that leads here opened for HR and the letter
        // it produced did not.
        if (!in_array('admin', user_roles()) && user()->permission('edit_employees') !== 'all') {
            abort(403);
        }

        return view('offer-letter.index', $this->data);
    }

    public function noticePeriodSettings()
    {
        $this->pageTitle = 'app.menu.noticePeriodSettings';
        $this->activeSettingMenu = 'notice_period_settings';

        abort_403(user()->permission('manage_company_setting') !== 'all');

        return view('notice-period-settings.index', $this->data);
    }

    public function probationPeriodRequests()
    {
        $this->pageTitle = 'app.menu.probationPeriodRequests';

        abort_403(!in_array(user()->permission('view_employees'), ['all', 'added', 'owned', 'both']));

        return view('probation-requests.index', $this->data);
    }

    public function employeeTermination()
    {
        $this->pageTitle = 'Employee Termination';

        abort_403(!in_array(user()->permission('edit_employees'), ['all']));

        return view('employee-termination.index', $this->data);
    }

    public function payslipEntry()
    {
        $this->pageTitle = 'app.menu.payslipEntry';

        // This browses departments and employees and jumps into any employee's
        // payslip, so it asks the same question the payslip screens ask.
        if (!in_array('admin', user_roles()) && user()->permission('manage_payroll') !== 'all') {
            abort(403);
        }

        return view('payslips.entry', $this->data);
    }

    public function payslips()
    {
        $this->pageTitle = 'app.menu.payslips';

        // Only allow users with role 'employee' to view this page
        if (!in_array('employee', user_roles())) {
            abort(403);
        }

        // This page embeds the payslip calculator, which holds whole-company
        // salary data. An employee asking for their payslip wants their own, so
        // that is what they are given; whoever runs payroll gets the calculator.
        if (!in_array('admin', user_roles()) && user()->permission('manage_payroll') !== 'all') {
            return redirect()->route('my-payslips.index');
        }

        if (request()->ajax()) {
            $html = view('payslips.ajax.index', $this->data)->render();
            return Reply::dataOnly(['status' => 'success', 'html' => $html, 'title' => trans('app.menu.payslips')]);
        }

        return view('payslips.index', $this->data);
    }
}
