@php
    $money = fn ($v) => '₹' . number_format((float) $v, 2);
    $lop = max(0, (float) $payslip->net_salary - (float) $payslip->attendance_net);
    $takeHome = (float) $payslip->basic + (float) $payslip->hra + (float) $payslip->ca + (float) $payslip->others
        - ((float) $payslip->pf + (float) $payslip->esi + (float) $payslip->pt + $lop)
        - ((float) ($payslip->employer_pf ?? $payslip->pf) + (float) ($payslip->employer_esi ?? $payslip->esi));
@endphp
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"></head>
<body style="margin:0; padding:24px; background:#f4f5f7; font-family:Arial,Helvetica,sans-serif; color:#1f2937;">
    <div style="max-width:560px; margin:auto; background:#fff; border-radius:8px; padding:28px;">
        <p style="margin:0 0 16px; font-size:15px;">Dear {{ $payslip->emp_name }},</p>

        <p style="margin:0 0 16px; font-size:14px; line-height:1.6;">
            Your payslip for <strong>{{ $payslip->period }}</strong> is attached.
        </p>

        <table style="width:100%; border-collapse:collapse; font-size:14px; margin:0 0 18px;">
            <tr>
                <td style="padding:8px 0; border-bottom:1px solid #eee;">Days paid</td>
                <td style="padding:8px 0; border-bottom:1px solid #eee; text-align:right;">
                    {{ rtrim(rtrim(number_format((float) $payslip->days_present, 1), '0'), '.') }}
                    of {{ rtrim(rtrim(number_format((float) $payslip->working_days, 1), '0'), '.') }}
                </td>
            </tr>
            @if ((float) ($payslip->leave_encashment ?? 0) > 0)
                <tr>
                    <td style="padding:8px 0; border-bottom:1px solid #eee;">Leave encashment</td>
                    <td style="padding:8px 0; border-bottom:1px solid #eee; text-align:right;">{{ $money($payslip->leave_encashment) }}</td>
                </tr>
            @endif
            <tr>
                <td style="padding:10px 0; font-weight:bold;">Net salary</td>
                <td style="padding:10px 0; text-align:right; font-weight:bold;">
                    {{ $money($takeHome + (float) ($payslip->leave_encashment ?? 0)) }}
                </td>
            </tr>
        </table>

        <p style="margin:0 0 16px; font-size:13px; color:#6b7280; line-height:1.6;">
            The attached PDF has the full breakdown. If anything looks wrong, reply to this message
            or speak to HR, quoting payslip {{ $payslip->payslip_number ?: ('#' . $payslip->id) }}.
        </p>

        <p style="margin:0; font-size:13px; color:#6b7280;">{{ $companyName }}</p>
    </div>
</body>
</html>
