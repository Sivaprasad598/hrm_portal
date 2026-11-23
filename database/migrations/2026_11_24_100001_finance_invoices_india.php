<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Invoices for India (app-modules/Finance, AppModules\Finance\Services\GstInvoice), on the app's
 * own invoices / invoice_items / invoice_settings tables:
 *   invoices          place of supply (GST state code), intra / inter-state, CGST / SGST / IGST,
 *                     financial year, when issued, and what it printed (details json: bill-to,
 *                     wording, bank, signatory - the "custom" part, like the Letter Generator)
 *   invoice_items     GST rate, tax on the line, unit
 *   invoice_settings  the company's GST state, bank details, signatory
 *   client_details    the client's GST state (place of supply)
 * Menus: Finance -> Invoices and Clients for Super Admin, Admin, CEO and CFO.
 * Idempotent; down() drops the columns (the menu grants stay: roles may have had them before).
 */
return new class extends Migration {

    private const ROLES = ['admin', 'manager_admin', 'ceo', 'cfo'];

    public function up(): void
    {
        $add = function (string $table, string $column, callable $define) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, $column)) {
                Schema::table($table, fn (Blueprint $t) => $define($t));
            }
        };

        $add('invoices', 'place_of_supply', fn ($t) => $t->string('place_of_supply', 2)->nullable());
        $add('invoices', 'supply_type', fn ($t) => $t->string('supply_type', 10)->nullable());
        $add('invoices', 'cgst', fn ($t) => $t->decimal('cgst', 15, 2)->default(0));
        $add('invoices', 'sgst', fn ($t) => $t->decimal('sgst', 15, 2)->default(0));
        $add('invoices', 'igst', fn ($t) => $t->decimal('igst', 15, 2)->default(0));
        $add('invoices', 'financial_year', fn ($t) => $t->string('financial_year', 7)->nullable()->index());
        $add('invoices', 'issued_at', fn ($t) => $t->timestamp('issued_at')->nullable());
        $add('invoices', 'cancel_reason', fn ($t) => $t->string('cancel_reason', 500)->nullable());
        $add('invoices', 'details', fn ($t) => $t->longText('details')->nullable());

        $add('invoice_items', 'gst_rate', fn ($t) => $t->decimal('gst_rate', 5, 2)->default(0));
        $add('invoice_items', 'tax_amount', fn ($t) => $t->decimal('tax_amount', 15, 2)->default(0));
        $add('invoice_items', 'unit_label', fn ($t) => $t->string('unit_label', 20)->nullable());

        $add('invoice_settings', 'state_code', fn ($t) => $t->string('state_code', 2)->nullable());
        $add('invoice_settings', 'bank_details', fn ($t) => $t->text('bank_details')->nullable());
        $add('invoice_settings', 'signatory_name', fn ($t) => $t->string('signatory_name', 120)->nullable());

        $add('client_details', 'state_code', fn ($t) => $t->string('state_code', 2)->nullable());

        // the company is in Andhra Pradesh (37) unless set otherwise
        if (Schema::hasColumn('invoice_settings', 'state_code')) {
            DB::table('invoice_settings')->whereNull('state_code')->update(['state_code' => '37']);
        }

        $this->menus(true);
    }

    public function down(): void
    {
        $this->menus(false);

        $drop = function (string $table, array $columns) {
            $have = array_values(array_filter($columns, fn ($c) => Schema::hasTable($table) && Schema::hasColumn($table, $c)));
            if ($have) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn($have));
            }
        };
        $drop('invoices', ['place_of_supply', 'supply_type', 'cgst', 'sgst', 'igst', 'financial_year', 'issued_at', 'cancel_reason', 'details']);
        $drop('invoice_items', ['gst_rate', 'tax_amount', 'unit_label']);
        $drop('invoice_settings', ['state_code', 'bank_details', 'signatory_name']);
        $drop('client_details', ['state_code']);
    }

    /** Finance (its Invoices entry) and Clients, for the finance roles. */
    private function menus(bool $give): void
    {
        if (!Schema::hasTable('sidebar_menus') || !Schema::hasTable('role_menus')) {
            return;
        }

        $invoices = DB::table('sidebar_menus')->where('route', 'invoices.index')->first(['id', 'parent_id']);
        $ids = array_filter([$invoices?->id, $invoices?->parent_id, DB::table('sidebar_menus')->where('route', 'clients.index')->value('id')]);
        $roles = DB::table('roles')->whereIn('name', self::ROLES)->pluck('id');

        foreach ($ids as $menuId) {
            foreach ($roles as $roleId) {
                $exists = DB::table('role_menus')->where('role_id', $roleId)->where('menu_id', $menuId)->exists();
                if ($give && !$exists) {
                    DB::table('role_menus')->insert(['role_id' => $roleId, 'menu_id' => $menuId, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }

        if ($give) {
            DB::table('sidebar_menus')->whereIn('id', $ids)->update(['is_active' => 1]);
        }

        if (method_exists(\App\Models\User::class, 'forgetAllMenuCaches')) {
            \App\Models\User::forgetAllMenuCaches();
        }
    }

};
