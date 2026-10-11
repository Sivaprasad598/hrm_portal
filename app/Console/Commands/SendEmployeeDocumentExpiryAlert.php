<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\EmployeeDocumentExpiry;
use App\Models\User;
use App\Notifications\EmployeeDocumentExpiryAlert;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendEmployeeDocumentExpiryAlert extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send-employee-document-expiry-alert';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send alerts for employee documents that are expiring soon';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting employee document expiry alert process...');

        // Get all companies
        Company::active()->select(['id', 'timezone'])->chunk(50, function ($companies) {
            foreach ($companies as $company) {
                $this->processCompanyDocuments($company);
            }
        });

        $this->info('Employee document expiry alert process completed.');
        return Command::SUCCESS;
    }

    /**
     * Process documents for a specific company
     *
     * @param Company $company
     * @return void
     */
    private function processCompanyDocuments(Company $company)
    {
        $now = now($company->timezone);
        
        // Get documents that are expiring within the alert period
        $expiringDocuments = EmployeeDocumentExpiry::where('company_id', $company->id)
            ->where('alert_enabled', 1)
            ->where('expiry_date', '>=', $now->toDateString())
            ->with(['user'])
            ->get();

        foreach ($expiringDocuments as $document) {
            $this->checkAndSendAlert($document, $now);
        }
    }

    /**
     * Check if document should trigger an alert and send notification
     *
     * @param EmployeeDocumentExpiry $document
     * @param Carbon $now
     * @return void
     */
    private function checkAndSendAlert(EmployeeDocumentExpiry $document, Carbon $now)
    {
        $alertDate = $document->expiry_date->subDays($document->alert_before_days);
        
        // Check if today is the alert date
        if ($alertDate->isSameDay($now)) {
            $this->sendNotification($document);
        }
    }

    /**
     * Send notification to relevant users
     *
     * @param EmployeeDocumentExpiry $document
     * @return void
     */
    private function sendNotification(EmployeeDocumentExpiry $document)
    {
        try {
            // Notify the document owner
            $document->user->notify(new EmployeeDocumentExpiryAlert($document, 'expiring_soon'));
            
            // Notify HR/Admin users
            $hrUsers = User::allAdmins($document->company_id);
            foreach ($hrUsers as $hrUser) {
                $hrUser->notify(new EmployeeDocumentExpiryAlert($document, 'expiring_soon_hr'));
            }
            
        } catch (\Exception $e) {
            $this->error("Failed to send alert for document ID {$document->id}: " . $e->getMessage());
        }
    }
}
