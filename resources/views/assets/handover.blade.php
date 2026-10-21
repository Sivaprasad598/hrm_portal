<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Asset Handover: {{ $employee->name }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #222; font-size: 13px; margin: 0; background: #f2f4f7; }
        .sheet { background: #fff; width: 210mm; min-height: 297mm; margin: 20px auto; padding: 18mm 16mm; box-shadow: 0 2px 10px rgba(0,0,0,.08); }
        .head { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #222; padding-bottom: 10px; }
        .head img { max-height: 48px; }
        .head .co { text-align: right; font-size: 12px; color: #555; }
        .head .co strong { font-size: 15px; color: #222; }
        h1 { font-size: 17px; text-align: center; letter-spacing: .5px; margin: 18px 0 14px; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; }
        .info td { padding: 4px 6px; vertical-align: top; }
        .info td.l { color: #666; width: 22%; }
        .items { margin-top: 14px; }
        .items th, .items td { border: 1px solid #999; padding: 6px; text-align: left; }
        .items th { background: #f0f0f0; font-size: 12px; }
        .terms { margin-top: 16px; font-size: 12px; line-height: 1.55; }
        .terms ol { padding-left: 18px; margin: 6px 0; }
        .sign { display: flex; justify-content: space-between; margin-top: 60px; }
        .sign div { width: 42%; border-top: 1px solid #222; padding-top: 6px; font-size: 12px; }
        .bar { text-align: center; margin: 16px 0 0; }
        .bar button { background: #1d82f5; color: #fff; border: 0; padding: 9px 22px; border-radius: 4px; font-size: 14px; cursor: pointer; }
        .muted { color: #666; }
        @media print {
            body { background: #fff; }
            .sheet { margin: 0; box-shadow: none; width: auto; min-height: auto; padding: 0; }
            .bar { display: none; }
            @page { size: A4; margin: 15mm; }
        }
    </style>
</head>
<body>
    <div class="bar"><button onclick="window.print()">Print / Save as PDF</button></div>

    <div class="sheet">
        <div class="head">
            <div>@if ($company->logo_url)<img src="{{ $company->logo_url }}" alt="">@endif</div>
            <div class="co">
                <strong>{{ $company->company_name }}</strong><br>
                {{ $company->company_email }}{{ $company->company_phone ? ' · ' . $company->company_phone : '' }}
            </div>
        </div>

        <h1>Asset Handover Acknowledgement</h1>

        <table class="info">
            <tr>
                <td class="l">Employee</td><td><strong>{{ $employee->name }}</strong></td>
                <td class="l">Employee ID</td><td>{{ $employee->employeeDetail?->employee_id ?: '--' }}</td>
            </tr>
            <tr>
                <td class="l">Designation</td><td>{{ $employee->employeeDetail?->designation?->name ?: '--' }}</td>
                <td class="l">Department</td><td>{{ $employee->employeeDetail?->department?->team_name ?: '--' }}</td>
            </tr>
            <tr>
                <td class="l">Date</td><td>{{ now(company()->timezone)->format(company()->date_format) }}</td>
                <td class="l"></td><td></td>
            </tr>
        </table>

        <table class="items">
            <thead>
                <tr><th style="width:4%">#</th><th>Asset</th><th>Code</th><th>Serial Number</th><th>Handed Over</th><th>Condition</th><th>Accessories / Notes</th></tr>
            </thead>
            <tbody>
            @forelse ($items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item->asset?->name }}{{ $item->asset?->type ? ' (' . $item->asset->type->name . ')' : '' }}
                        @if ($item->asset?->brand || $item->asset?->model)<br><span class="muted">{{ trim($item->asset->brand . ' ' . $item->asset->model) }}</span>@endif</td>
                    <td>{{ $item->asset?->asset_code }}</td>
                    <td>{{ $item->asset?->serial_number ?: '--' }}</td>
                    <td>{{ $item->assigned_on->format(company()->date_format) }}</td>
                    <td>{{ \App\Models\Asset::CONDITIONS[$item->condition_out] ?? ($item->condition_out ?: '--') }}</td>
                    <td>{{ $item->assign_notes ?: '--' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">No asset is with this employee.</td></tr>
            @endforelse
            </tbody>
        </table>

        <div class="terms">
            I acknowledge that I have received the asset(s) listed above in the condition stated, and I agree that:
            <ol>
                <li>The asset(s) remain the property of {{ $company->company_name }} and are for official use only.</li>
                <li>I will take reasonable care of them and report any damage, fault or loss to HR / IT immediately.</li>
                <li>I will return them, with their accessories, when asked, and in any case before my last working day.</li>
                <li>The cost of loss or damage caused by negligence may be recovered from me, including from my final settlement.</li>
            </ol>
        </div>

        <div class="sign">
            <div>Employee signature<br><span class="muted">{{ $employee->name }}</span></div>
            <div>Issued by (HR / IT)<br><span class="muted">{{ $items->first()?->assignedBy?->name ?? '' }}</span></div>
        </div>
    </div>
</body>
</html>
