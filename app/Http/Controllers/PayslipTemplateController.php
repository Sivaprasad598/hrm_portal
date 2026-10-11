<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Services\Payroll\PayslipTemplates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Which design payslips are printed in.
 *
 * The choice applies to payslips issued from now on. One already issued keeps
 * the design stamped on it when it was issued — a payslip is a document
 * somebody was given, and restyling it afterwards would change a record.
 */
class PayslipTemplateController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'app.menu.payslipSettings';
        $this->activeSettingMenu = 'payslip_settings';

        // Two different questions, so two different gates.
        //
        // Looking at a design is for whoever issues payslips in it: the Payroll
        // Run screen names the design beside the button that issues them, and
        // offering HR a preview they are refused is worse than not offering it.
        //
        // Changing it is a company setting, and stays one.
        $this->middleware(function ($request, $next) {
            abort_403(user()->permission('manage_payroll') !== 'all'
                && user()->permission('manage_company_setting') !== 'all');

            return $next($request);
        })->only('preview');

        $this->middleware(function ($request, $next) {
            abort_403(user()->permission('manage_company_setting') !== 'all');

            return $next($request);
        })->only('update');
    }

    /**
     * One template, drawn from sample figures.
     *
     * The real template file, not a picture of it, so the preview cannot drift
     * from what is actually issued.
     */
    public function preview(string $template, PayslipTemplates $templates)
    {
        abort_unless($templates->exists($template), 404);

        return response($templates->render($template))
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public function update(Request $request, PayslipTemplates $templates)
    {
        $request->validate(['template' => 'required|string|max:40']);

        abort_403(!$templates->exists($request->template));

        $company = company();
        $companyId = is_object($company) ? $company->id : null;

        $existing = DB::table('payslip_template_settings')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderBy('id')
            ->first(['id']);

        $values = [
            'template' => $request->template,
            'updated_by' => user()->id,
            'updated_at' => now(),
        ];

        $existing
            ? DB::table('payslip_template_settings')->where('id', $existing->id)->update($values)
            : DB::table('payslip_template_settings')->insert(
                $values + ['company_id' => $companyId, 'created_at' => now()]
            );

        return Reply::success(
            $templates->name($request->template)
            . ' will be used for payslips issued from now on. Payslips already issued keep the design they were issued in.'
        );
    }

}
