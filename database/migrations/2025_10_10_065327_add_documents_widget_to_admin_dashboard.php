<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\DashboardWidget;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add documents widget to admin dashboard (overview) for all companies
        $companies = \App\Models\Company::select('id')->get();
        
        foreach ($companies as $company) {
            DashboardWidget::updateOrCreate(
                [
                    'widget_name' => 'documents',
                    'dashboard_type' => 'admin-dashboard',
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
     */
    public function down(): void
    {
        // Remove documents widget from admin dashboard
        DashboardWidget::where('widget_name', 'documents')
            ->where('dashboard_type', 'admin-dashboard')
            ->delete();
    }
};
