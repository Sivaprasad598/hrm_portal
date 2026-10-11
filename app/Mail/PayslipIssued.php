<?php

namespace App\Mail;

use App\Services\Payroll\PayslipDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * An employee's payslip, as a PDF attachment.
 */
class PayslipIssued extends Mailable
{

    use Queueable, SerializesModels;

    public function __construct(
        public object $payslip,
        public string $companyName,
    ) {
    }

    public function build(): self
    {
        $document = new PayslipDocument();

        return $this->subject('Payslip for ' . $this->payslip->period . ' — ' . $this->companyName)
            ->view('emails.payslip-issued')
            ->attachData(
                $document->pdf((int) $this->payslip->id)->output(),
                $document->filename((int) $this->payslip->id),
                ['mime' => 'application/pdf']
            );
    }

}
