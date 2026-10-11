<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Passport;
use App\Models\VisaDetail;
use App\Models\User;
use App\Notifications\ImmigrationExpiryAlert;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendImmigrationExpiryAlert extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send-immigration-expiry-alert';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send alerts for passport and visa documents that are expiring soon';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting immigration expiry alert process...');

        // Get all companies
        Company::active()->select(['id', 'timezone'])->chunk(50, function ($companies) {
            foreach ($companies as $company) {
                $this->processCompanyImmigrationDocuments($company);
            }
        });

        $this->info('Immigration expiry alert process completed.');
        return Command::SUCCESS;
    }

    /**
     * Process immigration documents for a specific company
     *
     * @param Company $company
     * @return void
     */
    private function processCompanyImmigrationDocuments(Company $company)
    {
        $now = now($company->timezone);
        
        // Process Passports
        $this->processPassports($company, $now);
        
        // Process Visas
        $this->processVisas($company, $now);
    }

    /**
     * Process passport expiry alerts
     *
     * @param Company $company
     * @param Carbon $now
     * @return void
     */
    private function processPassports(Company $company, Carbon $now)
    {
        // Get passports that have alert enabled and are not expired
        $expiringPassports = Passport::where('company_id', $company->id)
            ->where('alert_before_months', '>', 0)
            ->where('expiry_date', '>=', $now->toDateString())
            ->with(['user'])
            ->get();
            foreach ($expiringPassports as $passport) {
            
            $this->checkAndSendPassportAlert($passport, $now);
        }
    }

    /**
     * Process visa expiry alerts
     *
     * @param Company $company
     * @param Carbon $now
     * @return void
     */
    private function processVisas(Company $company, Carbon $now)
    {
        // Get visas that have alert enabled and are not expired
        $expiringVisas = VisaDetail::where('company_id', $company->id)
            ->where('alert_before_months', '>', 0)
            ->where('expiry_date', '>=', $now->toDateString())
            ->with(['user'])
            ->get();

        foreach ($expiringVisas as $visa) {
            $this->checkAndSendVisaAlert($visa, $now);
        }
    }

    /**
     * Check if passport should trigger an alert and send notification
     *
     * @param Passport $passport
     * @param Carbon $now
     * @return void
     */
    private function checkAndSendPassportAlert(Passport $passport, Carbon $now)
    {
        $alertDate = $passport->expiry_date->subMonths($passport->alert_before_months);
        info($alertDate);
        info($now);
        // Check if today is within the alert period (allow for some flexibility)
        if ($alertDate->isSameDay($now)) {
            $this->sendPassportNotification($passport);
        }
    }

    /**
     * Check if visa should trigger an alert and send notification
     *
     * @param VisaDetail $visa
     * @param Carbon $now
     * @return void
     */
    private function checkAndSendVisaAlert(VisaDetail $visa, Carbon $now)
    {
        $alertDate = $visa->expiry_date->subMonths($visa->alert_before_months);
        info('visa alert date');
        info($alertDate);
        info($now);
        // Check if today is within the alert period (allow for some flexibility)
        if ($alertDate->isSameDay($now)) {
            $this->sendVisaNotification($visa);
        }
    }

    /**
     * Send passport notification to relevant users
     *
     * @param Passport $passport
     * @return void
     */
    private function sendPassportNotification(Passport $passport)
    {
        try {
            // Notify the passport owner
            $passport->user->notify(new ImmigrationExpiryAlert($passport, 'expiring_soon', 'passport'));
            
            // Notify HR/Admin users
            $hrUsers = User::allAdmins($passport->company_id);
            foreach ($hrUsers as $hrUser) {
                $hrUser->notify(new ImmigrationExpiryAlert($passport, 'expiring_soon_hr', 'passport'));
            }
            
        } catch (\Exception $e) {
            $this->error("Failed to send passport alert for ID {$passport->id}: " . $e->getMessage());
        }
    }

    /**
     * Send visa notification to relevant users
     *
     * @param VisaDetail $visa
     * @return void
     */
    private function sendVisaNotification(VisaDetail $visa)
    {
        try {
            // Notify the visa owner
            $visa->user->notify(new ImmigrationExpiryAlert($visa, 'expiring_soon', 'visa'));
            
            // Notify HR/Admin users
            $hrUsers = User::allAdmins($visa->company_id);
            foreach ($hrUsers as $hrUser) {
                $hrUser->notify(new ImmigrationExpiryAlert($visa, 'expiring_soon_hr', 'visa'));
            }
            
        } catch (\Exception $e) {
            $this->error("Failed to send visa alert for ID {$visa->id}: " . $e->getMessage());
        }
    }
}