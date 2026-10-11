<?php

namespace App\Console\Commands;

use App\Events\EventReminderEvent;
use App\Models\Company;
use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendEventReminder extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send-event-reminder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send event reminder to the attendees before time specified in database';

    /**
     * Execute the console command.
     *
     * @return mixed
     */

    public function handle()
    {
        $processed = false;

        $companyQuery = Company::active()
            ->select('companies.id', 'companies.timezone')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('events')
                    ->whereColumn('events.company_id', 'companies.id')
                    ->where('events.send_reminder', 'yes');
            });

        $companyQuery->chunkById(100, function ($companiesWithEvents) use (&$processed) {
            if ($companiesWithEvents->isEmpty()) {
                return;
            }

            $processed = true;

            $companiesById = $companiesWithEvents->keyBy('id');
            $chunkCompanyIds = $companiesWithEvents->modelKeys();

            $now = now();

            Event::query()
                ->with('attendee')
                ->select(
                    'id',
                    'event_name',
                    'label_color',
                    'where',
                    'description',
                    'start_date_time',
                    'end_date_time',
                    'repeat',
                    'send_reminder',
                    'remind_time',
                    'remind_type',
                    'company_id'
                )
                ->whereIn('company_id', $chunkCompanyIds)
                ->where('send_reminder', 'yes')
                ->where('start_date_time', '>=', $now)
                ->orderBy('id')
                ->chunkById(200, function ($events) use ($companiesById) {
                    foreach ($events as $event) {
                        $company = $companiesById->get($event->company_id);

                        if (!$company) {
                            continue;
                        }

                        $reminderDateTime = $this->calculateReminderDateTime($event, $company);

                        if ($reminderDateTime instanceof Carbon
                            && $reminderDateTime->equalTo(now($company->timezone)->startOfMinute())) {
                            event(new EventReminderEvent($event));
                        }
                    }
                });
        });

        if (!$processed) {
            $this->info('No company with event found');
        }

        return Command::SUCCESS;

    }

    public function calculateReminderDateTime(Event $event, $company): ?Carbon
    {
        $time = $event->remind_time;
        $type = $event->remind_type;

        $startStr = $event->start_date_time instanceof Carbon
            ? $event->start_date_time->format('Y-m-d H:i:s')
            : (string) $event->start_date_time;

        switch ($type) {
        case 'day':
            return Carbon::createFromFormat('Y-m-d H:i:s', $startStr, $company->timezone)->subDays($time);
        case 'hour':
            return Carbon::createFromFormat('Y-m-d H:i:s', $startStr, $company->timezone)->subHours($time);
        case 'minute':
            return Carbon::createFromFormat('Y-m-d H:i:s', $startStr, $company->timezone)->subMinutes($time);
        default:
            return null;
        }
    }

}
