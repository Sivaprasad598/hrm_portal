<?php

namespace App\Observers;

use App\Events\PromotionAddedEvent;
use App\Models\Promotion;
use App\Models\EmployeeDetails;
use App\Notifications\PromotionUpdated;
use Illuminate\Support\Facades\Notification;
use App\Models\Notification as ModelsNotification;

class PromotionObserver
{

    public function creating(Promotion $promotion)
    {
        if (company()) {
            $promotion->company_id = company()->id;
        }
    }

    public function created(Promotion $promotion)
    {
        EmployeeDetails::where('user_id', $promotion->employee_id)
        ->update(['designation_id' => $promotion->current_designation_id, 'department_id' => $promotion->current_department_id]);

        if (!isRunningInConsoleOrSeeding() && $promotion->send_notification == 'yes') {
            event(new PromotionAddedEvent($promotion));
        } else {
            $promotion->is_send = 0;
            $promotion->saveQuietly();
        }
    }

    public function updated(Promotion $promotion)
    {
        if ($promotion->send_notification == 'yes' && $promotion->is_send == 0) {
            event(new PromotionAddedEvent($promotion));
            $promotion->is_send = 1;
            $promotion->saveQuietly();
        }

        if ($promotion->send_notification == 'yes' && (
            $promotion->isDirty('current_designation_id') ||
            $promotion->isDirty('current_department_id') ||
            $promotion->isDirty('promotion')
        )) {

            $previousDesignationId = $promotion->getOriginal('current_designation_id');
            $previousDepartmentId = $promotion->getOriginal('current_department_id');

            Notification::send($promotion->employee, new PromotionUpdated($promotion, $previousDesignationId, $previousDepartmentId));
        }
    }

    public function deleting(Promotion $promotion)
    {
        ModelsNotification::where('type', 'App\Notifications\PromotionAdded')
            ->whereNull('read_at')
            ->where(function ($q) use ($promotion) {
                $q->where('data', 'like', '{"id":' . $promotion->id . ',%');
            })->delete();
    }

}
