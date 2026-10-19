<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An exit created by approving a resignation remembers which resignation it
 * came from, so undoing that approval removes exactly that exit (two
 * resignations with the same date used to be indistinguishable).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('employee_exits', 'resignation_id')) {
            Schema::table('employee_exits', function (Blueprint $table) {
                $table->unsignedBigInteger('resignation_id')->nullable()->after('company_id')->index();
            });
        }

        // Back-fill: approve() creates the exit in the same request that stamps
        // reviewed_at, so the two times are within a few seconds of each other.
        $resignations = DB::table('resignations')->where('status', 'approved')->whereNotNull('reviewed_at')->get();

        foreach ($resignations as $r) {
            $exitId = DB::table('employee_exits')
                ->where('user_id', $r->user_id)
                ->whereNull('resignation_id')
                ->whereDate('resignation_date', substr((string) $r->resignation_date, 0, 10))
                ->whereBetween('created_at', [
                    \Carbon\Carbon::parse($r->reviewed_at)->subSeconds(10),
                    \Carbon\Carbon::parse($r->reviewed_at)->addSeconds(10),
                ])
                ->orderBy('id')
                ->value('id');

            if ($exitId) {
                DB::table('employee_exits')->where('id', $exitId)->update(['resignation_id' => $r->id]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employee_exits', 'resignation_id')) {
            Schema::table('employee_exits', function (Blueprint $table) {
                $table->dropColumn('resignation_id');
            });
        }
    }
};
