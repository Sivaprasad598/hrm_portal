<?php

namespace App\Http\Controllers;

use App\Models\AiSetting;
use App\Services\Ai\AiClient;
use App\Services\Ai\ResumeExtractor;
use App\Services\Bank\IfscLookup;
use App\Services\Payroll\SalaryCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The helpers the Add / Edit Employee form calls while it is being filled:
 * read a resume with AI, look up an IFSC, and break a CTC down per month.
 * None of them saves anything.
 */
class EmployeeOnboardingController extends AccountBaseController
{
    public function __construct()
    {
        parent::__construct();

        $this->middleware(function ($request, $next) {
            $canAdd = in_array(user()->permission('add_employees'), ['all', 'added'], true);
            $canEdit = in_array(user()->permission('edit_employees'), ['all', 'added', 'owned', 'both'], true);
            abort_403(!$canAdd && !$canEdit);

            return $next($request);
        });
    }

    /** Read a resume PDF into the form's fields. */
    public function resume(Request $request): JsonResponse
    {
        $request->validate(['resume_file' => 'required|file|mimes:pdf|max:5120'], [
            'resume_file.mimes' => 'The resume must be a PDF.',
            'resume_file.max' => 'The resume can be at most 5 MB.',
        ]);

        $setting = AiSetting::forCompany(company()->id);

        if (!$setting->ready()) {
            return response()->json([
                'status' => 'fail',
                'message' => 'AI is not set up. Enable it and add an API key on Settings → AI Settings to use resume auto-fill.',
            ], 422);
        }

        try {
            $data = (new ResumeExtractor(new AiClient($setting)))->extract($request->file('resume_file'));
        } catch (\Throwable $e) {
            logger()->warning('Resume extraction failed', ['provider' => $setting->provider, 'error' => $e->getMessage()]);

            return response()->json(['status' => 'fail', 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'status' => 'success',
            'provider' => AiSetting::PROVIDERS[$setting->provider],
            'data' => $data,
        ]);
    }

    /** The bank and branch an IFSC belongs to. */
    public function ifsc(string $code, IfscLookup $lookup): JsonResponse
    {
        return response()->json($lookup->lookup($code));
    }

    /**
     * A CTC broken down per month by the payroll formula, for a full month
     * worked. The same calculator the payroll run uses, so the figures match.
     */
    public function salary(Request $request): JsonResponse
    {
        $request->validate([
            'ctc' => 'required|numeric|min:0|max:1000000000',
            'basic' => 'nullable|numeric|min:0|max:100',
        ]);

        $calculator = new SalaryCalculator();
        $basic = $request->filled('basic') && (float) $request->basic > 0 ? (float) $request->basic : null;
        $settings = $calculator->settings();

        // One month fully worked, as the calculation sheet lays it out. The
        // per-day rate is not fixed: it is Net divided by the days in that pay
        // cycle (21st to 20th), so 30 or 31.
        $pay = $calculator->forEmployee((float) $request->ctc, $basic, 31, 31.0, 0.0);
        $pay30 = $calculator->forEmployee((float) $request->ctc, $basic, 30, 30.0, 0.0);

        $round = fn ($v) => round((float) $v, 2);
        $pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
        $pfRates = $pct((float) $settings->pf_pct) . '% + ' . $pct((float) ($settings->pf_employer_pct ?? $settings->pf_pct)) . '%';
        $esiRates = $pct((float) $settings->esi_pct) . '% + ' . $pct((float) ($settings->esi_employer_pct ?? $settings->esi_pct)) . '%';

        return response()->json([
            'monthly' => $round($pay['gross']),
            'basic_pct' => $round($pay['basic_pct']),
            'earnings' => [
                ['Basic', $round($pay['basic'])],
                ['HRA', $round($pay['hra'])],
                ['Conveyance Allowance', $round($pay['ca'])],
                ['Leave Travel Allowance', $round($pay['lta'])],
                ['Children Education Allowance', $round($pay['cea'])],
                ['Special Allowance', $round($pay['special'])],
            ],
            // As on the sheet: PF and ESI are both shares, all taken off the gross.
            'deductions' => [
                ['PF (' . $pfRates . ' of Basic' . ((float) $settings->pf_wage_ceiling > 0 ? ' up to ' . number_format((float) $settings->pf_wage_ceiling) : '') . ')', $round($pay['pf'])],
                ['ESI (' . $esiRates . ' of Gross, up to ' . number_format((float) $settings->esi_threshold) . ')', $round($pay['esi'])],
                ['Professional tax', $round($pay['pt'])],
            ],
            'total_deductions' => $round($pay['pf'] + $pay['esi'] + $pay['pt']),
            'net' => $round($pay['final_net']),
            'per_day' => [['31-day cycle', $round($pay['per_day'])], ['30-day cycle', $round($pay30['per_day'])]],
            // Kept for anything still reading the older shape.
            'employer' => [['PF (employer)', $round($pay['pf_employer'])], ['ESI (employer)', $round($pay['esi_employer'])]],
            'take_home' => $round($pay['final_net']),
        ]);
    }
}
