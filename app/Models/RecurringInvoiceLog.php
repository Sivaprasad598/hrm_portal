<?php

namespace App\Models;

class RecurringInvoiceLog extends BaseModel
{

    protected $table = 'recurring_invoice_logs';

    protected $fillable = [
        'company_id',
        'recurring_invoice_id',
        'invoice_id',
        'status',
        'message',
        'response_code',
    ];

    public function recurring()
    {
        return $this->belongsTo(RecurringInvoice::class, 'recurring_invoice_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

}
