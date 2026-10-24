<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Salary revision letter</title>
<style>
    @page { margin: 16mm 18mm 18mm 18mm; }
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 10.5px; color: #1f2933; line-height: 1.55; margin: 0; }
    .page-break { page-break-after: always; }
    .head { width: 100%; border-collapse: collapse; }
    .head td { vertical-align: middle; padding: 0; }
    .head .logo { width: 170px; }
    .head .logo img { height: 46px; }
    .head .co { text-align: right; font-size: 9px; color: #52606d; line-height: 1.45; }
    .head .co strong { display: block; font-size: 12.5px; color: #12263f; letter-spacing: .2px; }
    .rule { height: 3px; background: #12263f; margin: 8px 0 1px; }
    .rule2 { height: 1px; background: #c9a227; margin-bottom: 14px; }
    .conf { text-align: right; font-size: 8.5px; letter-spacing: 1.2px; color: #9b2c2c; font-weight: bold; margin-bottom: 8px; }
    .meta { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    .meta td { padding: 0; vertical-align: top; }
    .to { line-height: 1.5; }
    .to .name { font-weight: bold; font-size: 11.5px; color: #12263f; }
    .subject { margin: 12px 0 12px; font-weight: bold; color: #12263f; }
    .subject span { border-bottom: 1px solid #12263f; padding-bottom: 1px; }
    p { margin: 0 0 9px; text-align: justify; }
    .hl { font-weight: bold; color: #12263f; }
    .sign { margin-top: 18px; }
    .sign img { height: 70px; margin: 4px 0 2px -6px; }
    .sign .who { font-weight: bold; color: #12263f; }
    h3 { font-size: 12px; color: #12263f; margin: 0 0 4px; letter-spacing: .3px; }
    .sub { font-size: 9px; color: #52606d; margin-bottom: 10px; }
    table.pay { width: 100%; border-collapse: collapse; font-size: 10px; }
    table.pay th { background: #12263f; color: #fff; font-weight: bold; padding: 6px 8px; text-align: right; }
    table.pay th:first-child { text-align: left; }
    table.pay td { padding: 5px 8px; border-bottom: 1px solid #e4e7eb; text-align: right; }
    table.pay td:first-child { text-align: left; }
    table.pay tr.group td { background: #f0f4f8; font-weight: bold; color: #12263f; text-align: left; font-size: 9.5px; letter-spacing: .4px; }
    table.pay tr.total td { font-weight: bold; border-top: 1.5px solid #12263f; border-bottom: 1.5px solid #12263f; background: #fbf8ec; }
    .summary { width: 100%; border-collapse: collapse; margin: 12px 0 14px; }
    .summary td { width: 25%; border: 1px solid #d9e2ec; padding: 7px 9px; vertical-align: top; }
    .summary .k { font-size: 8.5px; text-transform: uppercase; letter-spacing: .6px; color: #52606d; }
    .summary .v { font-size: 13px; font-weight: bold; color: #12263f; }
    .notes { font-size: 8.8px; color: #52606d; margin-top: 10px; }
    .notes li { margin-bottom: 2px; }
    .ack { margin-top: 22px; border: 1px dashed #9aa5b1; padding: 10px 12px; }
    .ack .t { font-weight: bold; color: #12263f; margin-bottom: 6px; }
    .ack table { width: 100%; }
    .ack td { width: 33%; font-size: 9.5px; color: #52606d; padding-top: 2px; border-top: 1px solid #9aa5b1; }
    .ack td.gap { border: 0; width: 4%; }
    .foot { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 8px; color: #9aa5b1; text-align: center; }
    .draft { position: fixed; top: 38%; left: 8%; font-size: 90px; color: rgba(155, 44, 44, 0.08); transform: rotate(-30deg); font-weight: bold; letter-spacing: 8px; }
</style>
</head>
<body>
@foreach ($pages as $page)
    {!! $page !!}
    @unless ($loop->last)<div class="page-break"></div>@endunless
@endforeach
</body>
</html>
