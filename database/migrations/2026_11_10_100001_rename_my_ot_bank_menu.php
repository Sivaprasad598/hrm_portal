<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "My OT Bank" reads as jargon: the sidebar calls it "My Overtime" (the page
 * itself is the Overtime Balance). Only the label changes - slug and route stay.
 * Built sidebars are cached per person, so those are cleared (only those - not
 * the whole cache).
 */
return new class extends Migration {
    public function up(): void
    {
        DB::table('sidebar_menus')->where('slug', 'my-ot-bank')->update(['name' => 'My Overtime', 'updated_at' => now()]);
        User::forgetAllMenuCaches();
    }

    public function down(): void
    {
        DB::table('sidebar_menus')->where('slug', 'my-ot-bank')->update(['name' => 'My OT Bank', 'updated_at' => now()]);
        User::forgetAllMenuCaches();
    }
};
