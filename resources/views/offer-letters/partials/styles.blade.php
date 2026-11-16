{{-- The letterhead's styles, shared by the offer and relieving letters (one template). --}}
<style>
    @page { margin: 10mm 15mm 32mm 15mm; }
    * { box-sizing: border-box; }
    body { font-family: "Times New Roman", Times, serif; font-size: 15px; color: #000; line-height: 1.3; margin: 0; }
    .sheet { position: relative; }
    .page-break { page-break-after: always; }
    .logo img { height: 52px; }
    .logo { margin-bottom: 14px; }
    p { margin: 0 0 11px; text-align: justify; }
    .meta div { margin-bottom: 12px; }
    .to { margin-bottom: 14px; }
    .subject { font-weight: bold; text-decoration: underline; margin-bottom: 14px; }
    ol.docs { margin: 0 0 4px; padding-left: 18px; }
    ol.docs li { margin: 0; }
    .note { margin-bottom: 14px; }
    .closing { margin-top: 14px; }
    .signoff { margin-top: 38px; }
    .encl { margin-top: 26px; }
    .stamp img { height: 90px; margin: 6px 0 0 -4px; }
    sup { font-size: 9px; line-height: 0; }

    table.ann { width: 88%; margin: 0 auto; border-collapse: collapse; font-size: 14px; }
    table.ann td { border: 1px solid #000; padding: 1px 6px; height: 19px; }
    table.ann td.n { text-align: right; width: 110px; }
    table.ann tr.band td { background: #f4b183; text-align: center; font-weight: bold; }
    table.ann tr.dark td { background: #404040; color: #fff; text-align: center; font-weight: bold; }
    table.ann tr.head td { text-align: center; font-weight: bold; }
    table.ann tr.b td { font-weight: bold; }
    .ack { margin-top: 16px; }
    .ack p { text-align: left; }
    .sigs { margin-top: 14px; font-weight: bold; line-height: 1.5; }

    .foot { position: fixed; left: 0; right: 0; bottom: -28mm; text-align: center; font-family: Calibri, Arial, Helvetica, sans-serif; }
    .foot .co { color: #c0603c; font-weight: bold; font-size: 16px; letter-spacing: .2px; }
    .foot .addr { font-size: 11px; margin: 2px 0 4px; }
    .foot table { width: 100%; font-size: 11px; border-collapse: collapse; }
    .foot td { width: 33%; }
    .foot .sym { font-family: DejaVu Sans, sans-serif; color: #c0603c; }
    .foot .bar { height: 5px; background: #c0603c; margin-top: 4px; }
    /* The browser view only (DomPDF reads "screen" styles too, so they hang off a class). */
    body.on-screen { background: #e9ecef; padding: 16px 0; }
    .on-screen .sheet { width: 210mm; min-height: 297mm; margin: 0 auto 16px; background: #fff; padding: 10mm 15mm 38mm; box-shadow: 0 1px 6px rgba(0,0,0,.15); }
    .on-screen .foot { position: absolute; bottom: 8mm; left: 16mm; right: 16mm; }
    .on-screen .page-break { page-break-after: auto; }
</style>
<style>
    /* The relieving letter's own lines */
    .r-date { text-align: right; font-weight: bold; margin: 18px 0 30px; }
    .r-title { text-align: center; font-weight: bold; text-decoration: underline; font-size: 16px; margin-bottom: 26px; }
    .r-to { margin-bottom: 18px; }
    .r-greet { margin-bottom: 14px; }
    .r-body p { margin-bottom: 14px; }
</style>
