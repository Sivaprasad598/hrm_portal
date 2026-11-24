<?php

namespace AppModules\Finance\Services;

use App\Services\Employee\OfferLetter;
use App\Services\Payroll\SalaryRevisionLetter;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Invoices as Indian GST wants them, on the app's invoices / invoice_items tables:
 *   - the supplier's and the recipient's GSTIN and state; the place of supply decides the tax:
 *     the same state as ours = CGST + SGST (half each), another state = IGST
 *   - HSN / SAC code, quantity, rate and GST rate on each line; a tax summary by code and rate
 *   - the total rounded to the rupee (round-off shown), and in words (lakh / crore)
 *   - numbered per financial year (April-March), PREFIX/26-27/001 - at most 16 characters,
 *     given when the invoice is issued; a draft has no number yet
 * What an issued invoice printed (bill-to, wording, bank, signatory) is kept with it (details).
 * Written with the query builder: the Invoice model's observer mails clients and reads the old
 * form's fields, which this does not use.
 */
class GstInvoice
{

    /** GST state codes. */
    public const STATES = [
        '01' => 'Jammu & Kashmir', '02' => 'Himachal Pradesh', '03' => 'Punjab', '04' => 'Chandigarh', '05' => 'Uttarakhand',
        '06' => 'Haryana', '07' => 'Delhi', '08' => 'Rajasthan', '09' => 'Uttar Pradesh', '10' => 'Bihar', '11' => 'Sikkim',
        '12' => 'Arunachal Pradesh', '13' => 'Nagaland', '14' => 'Manipur', '15' => 'Mizoram', '16' => 'Tripura', '17' => 'Meghalaya',
        '18' => 'Assam', '19' => 'West Bengal', '20' => 'Jharkhand', '21' => 'Odisha', '22' => 'Chhattisgarh', '23' => 'Madhya Pradesh',
        '24' => 'Gujarat', '26' => 'Dadra & Nagar Haveli and Daman & Diu', '27' => 'Maharashtra', '29' => 'Karnataka', '30' => 'Goa',
        '31' => 'Lakshadweep', '32' => 'Kerala', '33' => 'Tamil Nadu', '34' => 'Puducherry', '35' => 'Andaman & Nicobar Islands',
        '36' => 'Telangana', '37' => 'Andhra Pradesh', '38' => 'Ladakh', '97' => 'Other Territory', '96' => 'Outside India (export)',
    ];

    public const RATES = [0, 5, 12, 18, 28];

    public const TITLES = ['Tax Invoice', 'Proforma Invoice', 'Bill of Supply'];

    public const GSTIN = '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/';

    public static function canUse(): bool
    {
        return user() && ((bool) array_intersect(['admin', 'manager_admin', 'ceo', 'vice_president', 'cfo', 'hr'], user_roles())
            || user()->permission('view_invoices') === 'all');
    }

    /** The company's invoice settings, with India's defaults. */
    public static function settings(): object
    {
        $s = DB::table('invoice_settings')->where('company_id', company()->id)->first();
        $letter = OfferLetter::settings();

        return (object) [
            'prefix' => $s->invoice_prefix ?? 'INV',
            'digits' => max(1, min(6, (int) ($s->invoice_digit ?? 3))),
            'due_after' => (int) ($s->due_after ?? 15),
            'gstin' => strtoupper(trim((string) ($s->gst_number ?? ''))),
            'state_code' => $s->state_code ?? '37',
            'terms' => $s->invoice_terms ?? '',
            'bank' => $s->bank_details ?? '',
            'signatory' => $s->signatory_name ?? '',
            'company' => $letter['footer_company'],
            'address' => $letter['footer_address'],
            'phone' => $letter['footer_phone'],
            'email' => $letter['footer_email'],
            'website' => $letter['footer_website'],
        ];
    }

    /** 2026-11-24 -> "26-27" (April to March). */
    public static function financialYear(Carbon $date): string
    {
        $start = $date->month >= 4 ? $date->year : $date->year - 1;

        return sprintf('%02d-%02d', $start % 100, ($start + 1) % 100);
    }

    /** The next number in that financial year: INV/26-27/001. @return array{0: string, 1: int} */
    public static function nextNumber(Carbon $date, ?object $settings = null): array
    {
        $settings ??= self::settings();
        $fy = self::financialYear($date);
        $last = (int) DB::table('invoices')->where('company_id', company()->id)->where('financial_year', $fy)->where('status', '!=', 'draft')
            ->max(DB::raw('CAST(original_invoice_number AS UNSIGNED)'));
        $seq = $last + 1;

        return [$settings->prefix . '/' . $fy . '/' . str_pad((string) $seq, $settings->digits, '0', STR_PAD_LEFT), $seq];
    }

    /** GSTIN -> its state code ("37AAACA1234A1Z5" -> "37"). */
    public static function stateOf(?string $gstin): ?string
    {
        $gstin = strtoupper(trim((string) $gstin));

        return preg_match(self::GSTIN, $gstin) && isset(self::STATES[substr($gstin, 0, 2)]) ? substr($gstin, 0, 2) : null;
    }

    /**
     * The form, cleaned and worked out: lines with their tax, the totals, CGST / SGST or IGST.
     * @return array the invoice as it prints
     */
    public static function compute(array $in, ?object $settings = null): array
    {
        $settings ??= self::settings();
        $lines = [];
        foreach ((array) ($in['lines'] ?? []) as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $qty = round(max(0, (float) ($row['qty'] ?? 0)), 3);
            $rate = round(max(0, (float) ($row['rate'] ?? 0)), 2);
            $gst = in_array((float) ($row['gst'] ?? 0), array_map('floatval', self::RATES), true) ? (float) $row['gst'] : 18.0;
            $taxable = round($qty * $rate, 2);
            $lines[] = [
                'name' => mb_substr($name, 0, 190), 'description' => trim((string) ($row['description'] ?? '')),
                'hsn' => strtoupper(trim((string) ($row['hsn'] ?? ''))), 'unit' => trim((string) ($row['unit'] ?? '')),
                'qty' => $qty, 'rate' => $rate, 'gst' => $gst, 'taxable' => $taxable, 'tax' => round($taxable * $gst / 100, 2),
            ];
        }

        $pos = (string) ($in['place_of_supply'] ?? '');
        $pos = isset(self::STATES[$pos]) ? $pos : $settings->state_code;
        $intra = $pos === $settings->state_code;
        $billOfSupply = ($in['title'] ?? '') === 'Bill of Supply';

        $subTotal = round(array_sum(array_column($lines, 'taxable')), 2);
        $tax = $billOfSupply ? 0.0 : round(array_sum(array_column($lines, 'tax')), 2);
        $cgst = $intra ? round($tax / 2, 2) : 0.0;
        $sgst = $intra ? round($tax - $cgst, 2) : 0.0;
        $igst = $intra ? 0.0 : $tax;
        $exact = $subTotal + $tax;
        $total = round($exact);

        // the tax summary, by HSN / SAC and rate
        $summary = [];
        foreach ($lines as $l) {
            $key = $l['hsn'] . '|' . $l['gst'];
            $summary[$key] ??= ['hsn' => $l['hsn'], 'gst' => $l['gst'], 'taxable' => 0, 'tax' => 0];
            $summary[$key]['taxable'] += $l['taxable'];
            $summary[$key]['tax'] += $billOfSupply ? 0 : $l['tax'];
        }
        foreach ($summary as &$row) {   // split as the totals are: CGST rounded, SGST the rest
            $row['tax'] = round($row['tax'], 2);
            $row['cgst'] = $intra ? round($row['tax'] / 2, 2) : 0.0;
            $row['sgst'] = $intra ? round($row['tax'] - $row['cgst'], 2) : 0.0;
        }
        unset($row);

        $gstin = strtoupper(trim((string) ($in['client_gstin'] ?? '')));

        return [
            'title' => in_array($in['title'] ?? '', self::TITLES, true) ? $in['title'] : 'Tax Invoice',
            'lines' => $lines,
            'place_of_supply' => $pos,
            'supply_type' => $intra ? 'intra' : 'inter',
            'sub_total' => $subTotal, 'tax' => $tax, 'cgst' => $cgst, 'sgst' => $sgst, 'igst' => $igst,
            'round_off' => round($total - $exact, 2), 'total' => (float) $total,
            'summary' => array_values($summary),
            'bill_to' => [
                'name' => trim((string) ($in['client_name'] ?? '')),
                'address' => trim((string) ($in['client_address'] ?? '')),
                'gstin' => $gstin,
                'state' => self::stateOf($gstin) ?? $pos,
                'email' => trim((string) ($in['client_email'] ?? '')),
            ],
            'po' => trim((string) ($in['po'] ?? '')),
            'reverse_charge' => !empty($in['reverse_charge']),
            'intro' => trim((string) ($in['intro'] ?? '')),
            'notes' => trim((string) ($in['notes'] ?? '')),
            'terms' => trim((string) ($in['terms'] ?? '')),
            'bank' => trim((string) ($in['bank'] ?? '')),
            'signatory' => trim((string) ($in['signatory'] ?? '')),
            'show_stamp' => !empty($in['show_stamp']),
        ];
    }

    /** What is wrong with the form, as sentences (empty = can be issued). */
    public static function problems(array $c, array $in, bool $issuing): array
    {
        $p = [];
        if ($c['bill_to']['name'] === '') {
            $p[] = 'Say who it is billed to.';
        }
        if (!$c['lines']) {
            $p[] = 'Add at least one line.';
        }
        if ($c['bill_to']['gstin'] !== '' && !preg_match(self::GSTIN, $c['bill_to']['gstin'])) {
            $p[] = 'The client\'s GSTIN is not valid (15 characters, e.g. 37AAACA1234A1Z5).';
        }
        if ($issuing) {
            foreach ($c['lines'] as $i => $l) {
                if ($l['qty'] <= 0 || $l['rate'] <= 0) {
                    $p[] = 'Line ' . ($i + 1) . ' needs a quantity and a rate.';
                }
                if ($c['title'] === 'Tax Invoice' && $l['hsn'] === '') {
                    $p[] = 'Line ' . ($i + 1) . ' needs its HSN / SAC code.';
                }
            }
            if ($c['total'] <= 0) {
                $p[] = 'The total is nothing.';
            }
        }
        foreach (['issue_date' => 'invoice date', 'due_date' => 'due date'] as $field => $label) {
            if (empty($in[$field])) {
                $p[] = 'Give the ' . $label . '.';
            }
        }

        return array_values(array_unique($p));
    }

    /** "Rupees Twelve Thousand Three Hundred Forty and Fifty Paise Only" */
    public static function words(float $amount): string
    {
        $rupees = (int) floor($amount);
        $paise = (int) round(($amount - $rupees) * 100);

        return 'Rupees ' . SalaryRevisionLetter::words($rupees) . ($paise ? ' and ' . SalaryRevisionLetter::words($paise) . ' Paise' : '') . ' Only';
    }

    public static function money($v): string
    {
        $v = (float) $v;
        $paise = sprintf('%02d', (int) round(abs($v - (int) $v) * 100) % 100);

        return ($v < 0 ? '-' : '') . SalaryRevisionLetter::inr(floor(abs($v))) . '.' . $paise;
    }

    /** Everything the invoice sheet needs. */
    public static function sheet(array $c, array $meta, bool $forPdf): array
    {
        $s = self::settings();
        $letter = OfferLetter::settings();

        return [
            'c' => $c, 's' => $s, 'letter' => $letter, 'forPdf' => $forPdf,
            'number' => $meta['number'] ?? null,
            'issueDate' => !empty($meta['issue_date']) ? Carbon::parse($meta['issue_date']) : null,
            'dueDate' => !empty($meta['due_date']) ? Carbon::parse($meta['due_date']) : null,
            'status' => $meta['status'] ?? 'draft',
            'project' => $meta['project'] ?? null,
            'logo' => OfferLetter::logo($forPdf),
            'stamp' => $c['show_stamp'] ? OfferLetter::stamp($letter, $forPdf) : null,
            'states' => self::STATES,
            'money' => fn ($v) => self::money($v),
            'words' => self::words($c['total']),
        ];
    }

    public static function html(array $c, array $meta, bool $forPdf): string
    {
        return view('invoices.sheet', self::sheet($c, $meta, $forPdf))->render();
    }

    public static function pdf(array $c, array $meta)
    {
        return Pdf::loadHTML(self::html($c, $meta, true))->setPaper('a4')->setOption('dpi', 96)->setOption('isFontSubsettingEnabled', true);
    }

    /** An invoice as stored -> the form's shape (to edit, duplicate or print). */
    public static function toForm(object $invoice): array
    {
        $d = json_decode((string) $invoice->details, true) ?: [];
        $lines = DB::table('invoice_items')->where('invoice_id', $invoice->id)->where('type', 'item')->orderBy('field_order')->get()
            ->map(fn ($i) => ['name' => $i->item_name, 'description' => $i->item_summary, 'hsn' => $i->hsn_sac_code, 'unit' => $i->unit_label,
                'qty' => (float) $i->quantity, 'rate' => (float) $i->unit_price, 'gst' => (float) $i->gst_rate])->all();

        return [
            'title' => $d['title'] ?? 'Tax Invoice', 'lines' => $lines, 'place_of_supply' => $invoice->place_of_supply,
            'client_id' => $invoice->client_id, 'project_id' => $invoice->project_id,
            'client_name' => $d['bill_to']['name'] ?? '', 'client_address' => $d['bill_to']['address'] ?? '',
            'client_gstin' => $d['bill_to']['gstin'] ?? '', 'client_email' => $d['bill_to']['email'] ?? '',
            'po' => $d['po'] ?? '', 'reverse_charge' => !empty($d['reverse_charge']), 'intro' => $d['intro'] ?? '', 'notes' => $d['notes'] ?? '',
            'terms' => $d['terms'] ?? '', 'bank' => $d['bank'] ?? '', 'signatory' => $d['signatory'] ?? '', 'show_stamp' => !empty($d['show_stamp']),
            'issue_date' => $invoice->issue_date, 'due_date' => $invoice->due_date,
        ];
    }

}
