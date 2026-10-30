<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Services\Payroll\NetPay;
use App\Services\Payroll\PayslipDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The signed-in employee's payslips: the same ones, figures and PDF as My
 * Payslips - issued (approved or paid) slips only, never a draft or a revoked one.
 */
class PayslipController extends MobileController
{
    const COLUMNS = ['p.id', 'p.payslip_number', 'p.period', 'p.period_start', 'p.period_end',
        'p.gross', 'p.basic', 'p.hra', 'p.ca', 'p.lta', 'p.cea', 'p.others', 'p.pf', 'p.esi', 'p.pt', 'p.tds',
        'p.employer_pf', 'p.employer_esi', 'p.net_salary', 'p.attendance_net', 'p.leave_encashment',
        'p.encashed_days', 'p.working_days', 'p.days_present', 'p.lop_days', 'p.per_day',
        'p.approval_status', 'p.generated_at', 'p.paid_at', 'p.ot_summary'];

    public function index(Request $request)
    {
        $request->validate(['year' => 'nullable|integer|min:2000|max:2100']);

        $payslips = $this->mine()
            ->when($request->year, fn ($q, $year) => $q->whereYear('p.period_end', $year))
            ->orderByDesc('p.period_start')->orderByDesc('p.id')
            ->get(self::COLUMNS);

        $extras = $this->extras($payslips->pluck('id')->all());

        return $this->ok($payslips->map(function ($p) use ($extras) {
            [$earn, $deduct] = $this->extraTotals($extras, $p->id);

            return [
                'id' => $p->id,
                'payslip_number' => $p->payslip_number,
                'month' => $p->period,
                'period_start' => $p->period_start,
                'period_end' => $p->period_end,
                'net_pay' => round(NetPay::takeHome($p, $earn, $deduct) + (float) $p->leave_encashment, 2),
                'status' => $p->approval_status,
            ];
        })->values());
    }

    public function show(int $payslip)
    {
        $p = $this->findOrFail($payslip);
        $lines = $this->extras([$p->id]);
        $earningLines = $lines->where('kind', 'earning');
        $deductionLines = $lines->where('kind', 'deduction');
        $lop = NetPay::lop($p);
        $money = fn ($v) => round((float) $v, 2);

        $earnings = array_values(array_filter([
            ['label' => 'Basic', 'amount' => $money($p->basic)],
            ['label' => 'House Rent Allowance', 'amount' => $money($p->hra)],
            ['label' => 'Conveyance Allowance', 'amount' => $money($p->ca)],
            (float) $p->lta ? ['label' => 'Leave Travel Allowance', 'amount' => $money($p->lta)] : null,
            (float) $p->cea ? ['label' => "Children's Education Allowance", 'amount' => $money($p->cea)] : null,
            ['label' => 'Special Allowance', 'amount' => $money($p->others)],
        ]));

        foreach ($earningLines as $line) {
            $earnings[] = ['label' => $line->label, 'amount' => $money($line->amount)];
        }

        $deductions = array_values(array_filter([
            ['label' => 'Employee PF', 'amount' => $money($p->pf)],
            (float) $p->esi ? ['label' => 'Employee ESI', 'amount' => $money($p->esi)] : null,
            ['label' => 'Professional Tax', 'amount' => $money($p->pt)],
            (float) $p->tds ? ['label' => 'Income Tax (TDS)', 'amount' => $money($p->tds)] : null,
            $lop > 0 ? ['label' => 'Loss of Pay', 'amount' => $money($lop)] : null,
        ]));

        foreach ($deductionLines as $line) {
            $deductions[] = ['label' => $line->label, 'amount' => $money($line->amount)];
        }

        $employer = [
            ['label' => 'Employer PF', 'amount' => $money($p->employer_pf ?? $p->pf)],
            ['label' => 'Employer ESI', 'amount' => $money($p->employer_esi ?? $p->esi)],
        ];

        $netPay = NetPay::takeHome($p, (float) $earningLines->sum('amount'), (float) $deductionLines->sum('amount'));
        $ot = json_decode((string) $p->ot_summary, true);

        return $this->ok([
            'id' => $p->id,
            'payslip_number' => $p->payslip_number,
            'month' => $p->period,
            'period_start' => $p->period_start,
            'period_end' => $p->period_end,
            'status' => $p->approval_status,
            'issued_at' => $this->time($p->generated_at),
            'paid_at' => $this->time($p->paid_at),
            'attendance' => [
                'working_days' => (float) $p->working_days,
                'days_present' => (float) $p->days_present,
                'lop_days' => (float) ($p->lop_days ?? max(0, $p->working_days - $p->days_present)),
                'per_day' => $money($p->per_day),
            ],
            'earnings' => $earnings,
            'total_earnings' => $money(collect($earnings)->sum('amount')),
            'deductions' => $deductions,
            'total_deductions' => $money(collect($deductions)->sum('amount')),
            'employer_contributions' => $employer,
            'total_employer_contributions' => $money(collect($employer)->sum('amount')),
            'leave_encashment' => (float) $p->leave_encashment ? ['days' => (float) $p->encashed_days, 'amount' => $money($p->leave_encashment)] : null,
            'overtime' => $ot ? [
                'encashed_hours' => abs((float) ($ot['encashed_hours'] ?? 0)),
                'encashed_amount' => $money($ot['encashed_amount'] ?? 0),
                'lop_days_covered' => (float) ($ot['covered_days'] ?? 0),
                'balance_hours' => (float) ($ot['balance'] ?? 0),
            ] : null,
            'net_pay' => $money($netPay),
            'net_pay_with_encashment' => $money($netPay + (float) $p->leave_encashment),
            'download_url' => url('api/mobile/v1/payslips/' . $p->id . '/download'),
        ]);
    }

    public function download(int $payslip, PayslipDocument $document)
    {
        $this->findOrFail($payslip);

        return $document->pdf($payslip)->download($document->filename($payslip));
    }

    /** This employee's issued payslips, as My Payslips scopes them. */
    private function mine()
    {
        return DB::table('payslips as p')
            ->where('p.user_id', $this->me()->id)
            ->where('p.status', 'active')
            ->whereIn('p.approval_status', ['approved', 'paid'])
            ->whereNull('p.deleted_at');
    }

    private function findOrFail(int $id): object
    {
        $payslip = $this->mine()->where('p.id', $id)->first(self::COLUMNS);
        abort_if($payslip === null, 404);

        return $payslip;
    }

    private function extras(array $ids)
    {
        return DB::table('payslip_components')->whereIn('payslip_id', $ids ?: [0])->get(['payslip_id', 'kind', 'label', 'amount']);
    }

    private function extraTotals($extras, int $id): array
    {
        $lines = $extras->where('payslip_id', $id);

        return [(float) $lines->where('kind', 'earning')->sum('amount'), (float) $lines->where('kind', 'deduction')->sum('amount')];
    }
}
