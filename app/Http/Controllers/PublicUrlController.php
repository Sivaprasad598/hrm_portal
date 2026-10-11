<?php

namespace App\Http\Controllers;

use App\Events\ContractSignedEvent;
use App\Helper\Files;
use App\Helper\Reply;
use App\Http\Requests\Admin\Contract\SignRequest;
use App\Http\Requests\EstimateAcceptRequest;
use App\Models\AcceptEstimate;
use App\Models\Contract;
use App\Models\ContractSign;
use App\Models\Estimate;
use App\Models\EstimateItem;
use App\Models\EstimateItemImage;
use App\Models\Invoice;
use App\Models\InvoiceItemImage;
use App\Models\InvoiceItems;
use App\Models\SmtpSetting;
use App\Scopes\ActiveScope;
use App\Traits\UniversalSearchTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Nwidart\Modules\Facades\Module;

class PublicUrlController extends Controller
{

    use UniversalSearchTrait;

    /* Contract */
    public function contractView(Request $request, $hash)
    {
        $pageTitle = 'app.menu.contracts';
        $pageIcon = 'fa fa-file';
        $contract = Contract::where('hash', $hash)
            ->withoutGlobalScope(ActiveScope::class)
            ->firstOrFail()->withCustomFields();

        $company = $contract->company;
        $invoiceSetting = $contract->company->invoiceSetting;
        $fields = [];

        $getCustomFieldGroupsWithFields = $contract->getCustomFieldGroupsWithFields();

        if ($getCustomFieldGroupsWithFields) {
            $this->fields = $getCustomFieldGroupsWithFields->fields;
        }

        return view('contract', [
            'contract' => $contract,
            'company' => $company,
            'pageTitle' => $pageTitle,
            'pageIcon' => $pageIcon,
            'invoiceSetting' => $invoiceSetting,
            'fields' => $fields
        ]);
    }

    public function contractSign(SignRequest $request, $id)
    {
        $this->contract = Contract::with('signature')->findOrFail($id);
        $this->company = $this->contract->company;
        $this->invoiceSetting = $this->contract->company->invoiceSetting;

        if ($this->contract && $this->contract->signature) {
            return Reply::error(__('messages.alreadySigned'));
        }

        $sign = new ContractSign();
        $sign->company_id = $this->company->id;
        $sign->full_name = $request->first_name . ' ' . $request->last_name;
        $sign->contract_id = $this->contract->id;
        $sign->email = $request->email;
        $sign->place = $request->place;
        $sign->date = now();
        $imageName = null;

        if ($request->signature_type == 'signature') {
            $image = $request->signature;  // your base64 encoded
            $image = str_replace('data:image/png;base64,', '', $image);
            $image = str_replace(' ', '+', $image);
            $imageName = str_random(32) . '.' . 'jpg';

            Files::createDirectoryIfNotExist('contract/sign');

            File::put(public_path() . '/' . Files::UPLOAD_FOLDER . '/contract/sign/' . $imageName, base64_decode($image));
            Files::uploadLocalFile($imageName, 'contract/sign', $this->company->id);
        }
        else {
            if ($request->hasFile('image')) {
                $imageName = Files::uploadLocalOrS3($request->image, 'contract/sign', 300);
            }
        }

        $sign->signature = $imageName;
        $sign->save();

        event(new ContractSignedEvent($this->contract, $sign));

        return Reply::redirect(route('contracts.show', $this->contract->id));
    }

    public function contractDownload($id)
    {
        $contract = Contract::where('hash', $id)->firstOrFail()->withCustomFields();
        $company = $contract->company;
        $fields = [];

        $getCustomFieldGroupsWithFields = $contract->getCustomFieldGroupsWithFields();

        if ($getCustomFieldGroupsWithFields) {
            $this->fields = $getCustomFieldGroupsWithFields->fields;
        }

        $this->invoiceSetting = $contract->company->invoiceSetting;

        $pdf = app('dompdf.wrapper');
        $pdf->setOption('enable_php', true);
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('isRemoteEnabled', true);

        App::setLocale($this->invoiceSetting->locale ?? 'en');
        Carbon::setLocale($this->invoiceSetting->locale ?? 'en');

        $pdf->loadHTML(pdfFixArabic(view('contracts.contract-pdf', ['contract' => $contract, 'company' => $company, 'fields' => $fields, 'invoiceSetting' => $this->invoiceSetting])->render()));

        $filename = 'contract-' . $contract->id;

        return $pdf->download($filename . '.pdf');
    }

    public function estimateView($hash)
    {
        $estimate = Estimate::with('client', 'clientdetails', 'clientdetails.user.country', 'unit')->where('hash', $hash)->firstOrFail();
        $company = $estimate->company;
        $defaultAddress = $company->defaultAddress;
        $pageTitle = $estimate->estimate_number;
        $pageIcon = 'icon-people';
        $this->discount = 0;

        if ($estimate->discount > 0) {
            if ($estimate->discount_type == 'percent') {
                $this->discount = (($estimate->discount / 100) * $estimate->sub_total);
            }
            else {
                $this->discount = $estimate->discount;
            }
        }


        $taxList = array();

        $items = EstimateItem::whereNotNull('taxes')
            ->where('estimate_id', $estimate->id)
            ->get();

        foreach ($items as $item) {

            foreach (json_decode($item->taxes) as $tax) {
                $this->tax = EstimateItem::taxbyid($tax)->first();

                if ($this->tax) {
                    if (!isset($taxList[$this->tax->tax_name . ': ' . $this->tax->rate_percent . '%'])) {

                        if ($estimate->calculate_tax == 'after_discount' && $this->discount > 0) {
                            $taxList[$this->tax->tax_name . ': ' . $this->tax->rate_percent . '%'] = ($item->amount - ($item->amount / $estimate->sub_total) * $this->discount) * ($this->tax->rate_percent / 100);

                        }
                        else {
                            $taxList[$this->tax->tax_name . ': ' . $this->tax->rate_percent . '%'] = $item->amount * ($this->tax->rate_percent / 100);
                        }

                    }
                    else {
                        if ($estimate->calculate_tax == 'after_discount' && $this->discount > 0) {
                            $taxList[$this->tax->tax_name . ': ' . $this->tax->rate_percent . '%'] = $taxList[$this->tax->tax_name . ': ' . $this->tax->rate_percent . '%'] + (($item->amount - ($item->amount / $estimate->sub_total) * $this->discount) * ($this->tax->rate_percent / 100));

                        }
                        else {
                            $taxList[$this->tax->tax_name . ': ' . $this->tax->rate_percent . '%'] = $taxList[$this->tax->tax_name . ': ' . $this->tax->rate_percent . '%'] + ($item->amount * ($this->tax->rate_percent / 100));
                        }
                    }
                }
            }
        }

        $taxes = $taxList;

        $this->invoiceSetting = $company->invoiceSetting;

        return view('estimate', [
            'estimate' => $estimate,
            'taxes' => $taxes,
            'company' => $company,
            'discount' => $this->discount,
            'pageTitle' => $pageTitle,
            'pageIcon' => $pageIcon,
            'invoiceSetting' => $this->invoiceSetting,
            'defaultAddress' => $defaultAddress
        ]);

    }

    public function estimateAccept(EstimateAcceptRequest $request, $id)
    {
        DB::beginTransaction();

        $estimate = Estimate::with('sign', 'items')->findOrFail($id);
        $company = $estimate->company;

        /** @phpstan-ignore-next-line */
        if ($estimate && $estimate->sign) {
            return Reply::error(__('messages.alreadySigned'));
        }

        $accept = new AcceptEstimate();
        $accept->company_id = $estimate->company->id;
        $accept->full_name = $request->first_name . ' ' . $request->last_name;
        $accept->estimate_id = $estimate->id;
        $accept->email = $request->email;
        $imageName = null;

        if ($request->signature_type == 'signature') {
            $image = $request->signature;  // your base64 encoded
            $image = str_replace('data:image/png;base64,', '', $image);
            $image = str_replace(' ', '+', $image);
            $imageName = str_random(32) . '.' . 'jpg';

            Files::createDirectoryIfNotExist('estimate/accept');

            File::put(public_path() . '/' . Files::UPLOAD_FOLDER . '/estimate/accept/' . $imageName, base64_decode($image));
            Files::uploadLocalFile($imageName, 'estimate/accept', $estimate->company_id);
        }
        else {
            if ($request->hasFile('image')) {
                $imageName = Files::uploadLocalOrS3($request->image, 'estimate/accept/', 300);
            }
        }

        $accept->signature = $imageName;
        $accept->save();

        $itemResponses = (array) $request->input('item_responses', []);
        $acceptedItemIds = [];

        foreach ($estimate->items as $estimateItem) {
            $response = $itemResponses[$estimateItem->id] ?? $estimateItem->client_response ?? 'yes';
            $response = in_array($response, ['yes', 'no']) ? $response : 'yes';

            $estimateItem->client_response = $response;
            $estimateItem->save();

            if ($response === 'yes') {
                $acceptedItemIds[] = $estimateItem->id;
            }
        }

        $acceptedItems = collect($estimate->items)->whereIn('id', $acceptedItemIds)->values();
        $totals = $this->calculateInvoiceTotalsFromEstimateItems($estimate, $acceptedItems);

        $estimate->sub_total = $totals['sub_total'];
        $estimate->discount = $totals['discount_value'];
        $estimate->total = $totals['total'];
        $estimate->status = 'accepted';
        $estimate->saveQuietly();

        $invoice = new Invoice();

        $invoice->company_id = $company->id;
        $invoice->client_id = $estimate->client_id;
        $invoice->issue_date = now($company->timezone)->format('Y-m-d');
        $invoice->due_date = now($company->timezone)->addDays($company->invoiceSetting->due_after)->format('Y-m-d');
        $invoice->sub_total = $totals['sub_total'];
        $invoice->discount = $totals['discount_value'];
        $invoice->discount_type = $estimate->discount_type;
        $invoice->total = $totals['total'];
        $invoice->currency_id = $estimate->currency_id;
        $invoice->note = trim_editor($estimate->note);
        $invoice->status = 'unpaid';
        $invoice->estimate_id = $estimate->id;
        $invoice->invoice_number = Invoice::lastInvoiceNumber() + 1;
        $invoice->save();

        /** @phpstan-ignore-next-line */
        foreach ($acceptedItems as $item) :

            if (!is_null($item)) {
                $invoiceItem = InvoiceItems::create(
                    [
                        'invoice_id' => $invoice->id,
                        'item_name' => $item->item_name,
                        'item_summary' => $item->item_summary ?: '',
                        'type' => 'item',
                        'quantity' => $item->quantity,
                        'unit_price' => round($item->unit_price, 2),
                        'amount' => round($item->amount, 2),
                        'taxes' => $item->taxes
                    ]
                );

                $estimateItemImage = $item->estimateItemImage;

                if(!is_null($estimateItemImage)) {

                    $file = new InvoiceItemImage();

                    $file->invoice_item_id = $invoiceItem->id;

                    $fileName = Files::generateNewFileName($estimateItemImage->filename);

                    Files::copy(EstimateItemImage::FILE_PATH . '/' . $estimateItemImage->item->id . '/' . $estimateItemImage->hashname, InvoiceItemImage::FILE_PATH . '/' . $invoiceItem->id . '/' . $fileName);

                    $file->filename = $estimateItemImage->filename;
                    $file->hashname = $fileName;
                    $file->size = $estimateItemImage->size;
                    $file->save();
                }
            }

        endforeach;

        // Log search
        $this->logSearchEntry($invoice->id, $invoice->invoice_number, 'invoices.show', 'invoice');

        DB::commit();

        return Reply::success(__('messages.estimateSigned'));
    }

    private function calculateInvoiceTotalsFromEstimateItems(Estimate $estimate, $items): array
    {
        $items = collect($items);
        $subTotal = round((float) $items->sum('amount'), 2);
        $discountValue = 0.0;

        if ($estimate->discount_type === 'percent') {
            $discountValue = round((($estimate->discount / 100) * $subTotal), 2);
            $invoiceDiscount = round((float) $estimate->discount, 2);
        }
        else {
            $discountValue = min(round((float) $estimate->discount, 2), $subTotal);
            $invoiceDiscount = $discountValue;
        }

        $taxTotal = 0.0;

        foreach ($items as $item) {
            if (is_null($item->taxes)) {
                continue;
            }

            $itemTaxableAmount = (float) $item->amount;

            if ($estimate->calculate_tax === 'after_discount' && $discountValue > 0 && $subTotal > 0) {
                $itemTaxableAmount -= (($item->amount / $subTotal) * $discountValue);
            }

            foreach (json_decode($item->taxes) as $tax) {
                $estimateTax = EstimateItem::taxbyid($tax)->first();

                if ($estimateTax) {
                    $taxTotal += ($itemTaxableAmount * ($estimateTax->rate_percent / 100));
                }
            }
        }

        $total = round(max(($subTotal - $discountValue), 0) + $taxTotal, 2);

        return [
            'sub_total' => $subTotal,
            'discount_value' => $invoiceDiscount,
            'total' => $total,
        ];
    }

    public function estimateDecline(Request $request, $id)
    {
        $estimate = Estimate::with('items')->findOrFail($id);
        $itemResponses = (array) $request->input('item_responses', []);
        $acceptedItemIds = [];

        foreach ($estimate->items as $estimateItem) {
            $response = $itemResponses[$estimateItem->id] ?? $estimateItem->client_response ?? 'yes';
            $response = in_array($response, ['yes', 'no']) ? $response : 'yes';
            $estimateItem->client_response = $response;
            $estimateItem->save();

            if ($response === 'yes') {
                $acceptedItemIds[] = $estimateItem->id;
            }
        }

        $acceptedItems = collect($estimate->items)->whereIn('id', $acceptedItemIds)->values();
        $totals = $this->calculateInvoiceTotalsFromEstimateItems($estimate, $acceptedItems);

        $estimate->sub_total = $totals['sub_total'];
        $estimate->discount = $totals['discount_value'];
        $estimate->total = $totals['total'];
        $estimate->status = 'declined';
        $estimate->saveQuietly();

        return Reply::dataOnly(['status' => 'success']);
    }

    public function estimateDownload($id)
    {
        $this->estimate = Estimate::with('client', 'clientdetails')->where('hash', $id)->firstOrFail();
        $this->invoiceSetting = $this->estimate->company->invoiceSetting;
        App::setLocale($this->invoiceSetting->locale ?? 'en');
        Carbon::setLocale($this->invoiceSetting->locale ?? 'en');

        $pdfOption = $this->domPdfObjectForDownload($id);
        $pdf = $pdfOption['pdf'];
        $filename = $pdfOption['fileName'];
        $filename = str_replace(['/', '\\'], '-', $filename);
        return $pdf->download($filename . '.pdf');

    }

    public function domPdfObjectForDownload($id)
    {
        $this->estimate = Estimate::with('items.estimateItemImage', 'items.unit')->where('hash', $id)->firstOrFail();
        $this->company = $this->estimate->company;
        $this->invoiceSetting = $this->company->invoiceSetting;
        App::setLocale($this->invoiceSetting->locale ?? 'en');
        Carbon::setLocale($this->invoiceSetting->locale ?? 'en');

        $pdfItems = $this->estimate->items->where('type', 'item');

        // After acceptance/decline, PDF should only show items client selected as "yes".
        if (in_array($this->estimate->status, ['accepted', 'declined'])) {
            $pdfItems = $pdfItems->filter(function ($item) {
                return ($item->client_response ?? 'yes') !== 'no';
            });
        }

        $this->estimate->setRelation('items', $pdfItems->values());

        $this->discount = 0;

        if ($this->estimate->discount > 0) {

            if ($this->estimate->discount_type == 'percent') {
                $this->discount = (($this->estimate->discount / 100) * $this->estimate->sub_total);
            }
            else {
                $this->discount = $this->estimate->discount;
            }
        }


        $taxList = array();

        $items = $this->estimate->items->whereNotNull('taxes');

        foreach ($items as $item) {

            foreach (json_decode($item->taxes) as $tax) {
                $this->tax = EstimateItem::taxbyid($tax)->first();

                if ($this->tax) {
                    if (!isset($taxList[$this->tax->tax_name . ': ' . $this->tax->rate_percent . '%'])) {

                        if ($this->estimate->calculate_tax == 'after_discount' && $this->discount > 0 && $this->estimate->sub_total > 0) {
                            $taxList[$this->tax->tax_name . ': ' . $this->tax->rate_percent . '%'] = ($item->amount - ($item->amount / $this->estimate->sub_total) * $this->discount) * ($this->tax->rate_percent / 100);

                        }
                        else {
                            $taxList[$this->tax->tax_name . ': ' . $this->tax->rate_percent . '%'] = $item->amount * ($this->tax->rate_percent / 100);
                        }

                    }
                    else {
                        if ($this->estimate->calculate_tax == 'after_discount' && $this->discount > 0 && $this->estimate->sub_total > 0) {
                            $taxList[$this->tax->tax_name . ': ' . $this->tax->rate_percent . '%'] = $taxList[$this->tax->tax_name . ': ' . $this->tax->rate_percent . '%'] + (($item->amount - ($item->amount / $this->estimate->sub_total) * $this->discount) * ($this->tax->rate_percent / 100));

                        }
                        else {
                            $taxList[$this->tax->tax_name . ': ' . $this->tax->rate_percent . '%'] = $taxList[$this->tax->tax_name . ': ' . $this->tax->rate_percent . '%'] + ($item->amount * ($this->tax->rate_percent / 100));
                        }
                    }
                }
            }
        }

        $this->taxes = $taxList;

        $pdf = app('dompdf.wrapper');
        $pdf->setOption('enable_php', true);
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('isRemoteEnabled', true);

        $pdf->loadHTML(pdfFixArabic(view('estimates.pdf.' . $this->invoiceSetting->template, $this->data)->render()));

        $filename = $this->estimate->estimate_number;

        return [
            'pdf' => $pdf,
            'fileName' => $filename
        ];
    }


}
