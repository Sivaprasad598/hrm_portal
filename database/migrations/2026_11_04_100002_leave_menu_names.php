<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Leave Management's pages named for what they are: the applications to decide
 * (Leave Requests), each employee's taken / remaining leave (Leave Balances) and
 * closing the leave year (Year-End Settlement).
 */
return new class extends Migration
{
    private const NAMES = [
        'leaves-index' => ['Leaves', 'Leave Requests'],
        'leave-management-index' => ['Leave Management', 'Leave Balances'],
        'leave-year-end-index' => ['Leave Year End', 'Year-End Settlement'],
    ];

    public function up(): void
    {
        foreach (self::NAMES as $slug => [, $new]) {
            DB::table('sidebar_menus')->where('slug', $slug)->update(['name' => $new, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach (self::NAMES as $slug => [$old]) {
            DB::table('sidebar_menus')->where('slug', $slug)->update(['name' => $old]);
        }
    }
};
