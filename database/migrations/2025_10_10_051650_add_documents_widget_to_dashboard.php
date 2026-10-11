<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\DashboardWidget;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add documents widget to private dashboard for all companies
        $companies = \App\Models\Company::select('id')->get();
        
        foreach ($companies as $company) {
            DashboardWidget::updateOrCreate(
                [
                    'widget_name' => 'documents',
                    'dashboard_type' => 'private-dashboard',
                    'company_id' => $company->id
                ],
                [
                    'status' => 1
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Remove documents widget from private dashboard
        DashboardWidget::where('widget_name', 'documents')
            ->where('dashboard_type', 'private-dashboard')
            ->delete();
    }
};