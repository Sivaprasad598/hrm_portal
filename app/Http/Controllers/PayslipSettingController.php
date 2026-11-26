<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Models\Branch;
use App\Http\Requests\Branch\StorePayslipBranch;
use App\Http\Requests\Branch\UpdatePayslipBranch;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class PayslipSettingController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'app.menu.payslipSettings';
        $this->activeSettingMenu = 'payslip_settings';
        $this->middleware(function ($request, $next) {
            abort_403(user()->permission('manage_company_setting') !== 'all' && !\App\Services\Access\PayrollAccess::can('settings'));

            return $next($request);
        });
    }

    public function index()
    {
        $this->branches = Branch::latest()->get();
        $this->payrollCalculations = DB::table('payroll_calculations')->orderBy('id', 'desc')->get();

        // The designs a payslip can be printed in, and the one new payslips
        // will use.
        $templates = app(\App\Services\Payroll\PayslipTemplates::class);
        $this->payslipTemplates = $templates->all();
        $this->payslipTemplate = $templates->selected();

        $this->useBiometricAttendance = \App\Services\Payroll\AttendanceGate::useBiometric(company()?->id);
        $this->deductLateComings = \App\Services\Payroll\AttendanceGate::deductLateComings(company()?->id);
        $this->prorateStatutory = (bool) (DB::table('payroll_calculations')->orderByDesc('id')->value('prorate_statutory') ?? true);

        return view('payslip-settings.index', $this->data);
    }

    /**
     * Payroll Calculation switches: "Deduct late comings" (payroll_options) and "Reduce PF / ESI for unpaid days"
     * (the payroll calculation in use, which the salary calculator reads).
     */
    public function saveCalculationSwitch(\Illuminate\Http\Request $request)
    {
        $request->validate(['switch' => 'required|in:deduct_late_comings,prorate_statutory']);
        $on = $request->boolean('on');

        if ($request->switch === 'deduct_late_comings') {
            \App\Services\Payroll\AttendanceGate::setOption(company()?->id, 'deduct_late_comings', $on);
        } else {
            $id = DB::table('payroll_calculations')->orderByDesc('id')->value('id');
            $id && DB::table('payroll_calculations')->where('id', $id)->update(['prorate_statutory' => $on]);
        }

        return Reply::success(__('messages.updateSuccess'));
    }

    /** Payroll Calculation -> "Use biometric attendance for payroll" (AttendanceGate). */
    public function saveAttendanceSource(\Illuminate\Http\Request $request)
    {
        \App\Services\Payroll\AttendanceGate::setUseBiometric(company()?->id, $request->boolean('use_biometric_attendance'));

        return Reply::success(__('messages.updateSuccess'));
    }

    public function create()
    {
        return view('payslip-settings.create', $this->data);
    }

    public function store(StorePayslipBranch $request)
    {
        $branch = new Branch();
        $branch->branch_name = $request->branch_name;
        $branch->address = $request->address;
        $branch->emails = array_values(array_filter($request->branch_email));
        $branch->phones = $this->formatPhones($request->branch_phone);
        $branch->save();

        return Reply::success(__('messages.recordSaved'));
    }

    public function edit($id)
    {
        $this->branch = Branch::findOrFail($id);

        return view('payslip-settings.edit', $this->data);
    }

    public function update(UpdatePayslipBranch $request, $id)
    {
        $branch = Branch::findOrFail($id);
        $branch->branch_name = $request->branch_name;
        $branch->address = $request->address;
        $branch->emails = array_values(array_filter($request->branch_email));
        $branch->phones = $this->formatPhones($request->branch_phone);
        $branch->save();

        return Reply::success(__('messages.updateSuccess'));
    }

    public function destroy($id)
    {
        try {
            Branch::destroy($id);
        } catch (QueryException) {
            return Reply::error(__('messages.branchInUse'));
        }

        return Reply::success(__('messages.deleteSuccess'));
    }

    public function destroyPayrollCalculation($id)
    {
        DB::table('payroll_calculations')->where('id', $id)->delete();

        return Reply::success(__('messages.deleteSuccess'));
    }

    /**
     * Prefix each submitted 10-digit phone number with the fixed +91 country code.
     */
    private function formatPhones($phones)
    {
        return array_values(array_map(
            fn ($digits) => '+91 ' . $digits,
            array_filter((array) $phones)
        ));
    }

}
