<?php

namespace App\Observers;

use App\Models\CustomField;
use App\Models\CustomFieldGroup;
use AppModules\Leads\Models\LeadCustomForm;
use AppModules\Tickets\Models\TicketCustomForm;
use Illuminate\Support\Facades\DB;

class CustomFieldsObserver
{

    public function creating(CustomField $model)
    {
        if (company()) {
            $model->company_id = company()->id;
        }
    }

    public function created(CustomField $customField)
    {
        $this->lead($customField);
        $this->ticket($customField);
    }

    private function lead($customField)
    {
        $lead = CustomFieldGroup::where('name', 'Lead')->first();

        if (!$lead || $customField->custom_field_group_id != $lead->id || !class_exists(\AppModules\Leads\Models\Lead::class)) {
            return false;
        }

        $leadField = new LeadCustomForm();
        $leadField->required = ($customField->required == 'yes') ? 1 : 0;
        $leadField->field_display_name = str($customField->label);
        $leadField->custom_fields_id = $customField->id;
        $leadField->field_name = $customField->name;
        $leadField->field_order = LeadCustomForm::max('field_order');
        $leadField->save();
    }

    private function ticket($customField)
    {
        $ticket = CustomFieldGroup::where('name', 'Ticket')->first();

        if (!$ticket || $customField->custom_field_group_id != $ticket->id || !class_exists(\AppModules\Tickets\Models\Ticket::class)) {
            return false;
        }

        $ticketField = new TicketCustomForm();
        $ticketField->required = ($customField->required == 'yes') ? 1 : 0;
        $ticketField->field_display_name = str($customField->label);
        $ticketField->custom_fields_id = $customField->id;
        $ticketField->field_name = $customField->name;
        $ticketField->field_type = $customField->type;
        $ticketField->field_order = TicketCustomForm::max('field_order');
        $ticketField->save();
    }

    public function updated(CustomField $customField)
    {
        $lead = CustomFieldGroup::where('name', 'Lead')->first();


        if ($lead && $customField->custom_field_group_id == $lead->id && class_exists(\AppModules\Leads\Models\Lead::class)) {
            $id = $customField->id;
            $leadField = LeadCustomForm::firstWhere('custom_fields_id', $id);
            $leadField->required = ($customField->required == 'yes') ? 1 : 0;
            $leadField->field_display_name = str($customField->label);
            $leadField->field_name = $customField->name;
            $leadField->save();
        }

        $ticket = CustomFieldGroup::where('name', 'Ticket')->first();

        if ($ticket && $customField->custom_field_group_id === $ticket->id && class_exists(\AppModules\Tickets\Models\Ticket::class)) {
            $id = $customField->id;
            $ticketField = TicketCustomForm::firstWhere('custom_fields_id', $id);

            $ticketField->required = ($customField->required == 'yes') ? 1 : 0;
            $ticketField->field_display_name = str($customField->label);
            $ticketField->custom_fields_id = $customField->id;
            $ticketField->field_name = $customField->name;
            $ticketField->field_type = $customField->type;
            $ticketField->save();
        }

        // remove select values that is deleted from custom field
        if ($customField->type == 'select') {
            $valuesIndexCount = count(json_decode($customField->values)) - 1;

            // delete values that is greater than the index count
            DB::table('custom_fields_data')->where('custom_field_id', $customField->id)->where('value', '>', $valuesIndexCount)->delete();
        }

    }

}

