<?php

namespace Database\Seeders;

use App\Models\AppLauncher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The launcher tiles that were hard-coded in sections/app-launcher.blade.php,
 * as rows the App Launcher Settings screen can reorder, recolour and switch
 * off.
 *
 * The slugs match the ones now carried by the hand-written $tiles array, which
 * is what keeps each tile behind the permission check it already had. Seeding
 * these changes nothing on screen: same tiles, same order, same colours.
 *
 * Re-runnable — rows are matched on slug.
 */
class AppLauncherSeeder extends Seeder
{

    /**
     * Name, slug, route, icon — in the order the launcher showed them.
     * Colours follow the same palette the view cycled through.
     */
    protected array $tiles = [
        ['Dashboard', 'dashboard', 'dashboard', 'home'],
        ['My Calendar', 'my-calendar', 'my-calendar.index', 'calendar-alt'],
        ['Leads', 'leads', 'lead-contact.index', 'bullseye'],
        ['Clients', 'clients', 'clients.index', 'building'],
        ['HR', 'hr', 'employees.index', 'users'],
        ['Employees', 'employees', 'employees.index', 'user-tie'],
        ['Leaves', 'leaves', 'leaves.index', 'plane-departure'],
        ['Leave Management', 'leave-mgmt', 'leave-management.index', 'calendar-check'],
        ['Identity Card', 'identity', 'identity.index', 'id-card'],
        ['Payslips', 'payslips', 'payslips.index', 'file-invoice-dollar'],
        ['Shift Roster', 'shift-roster', 'shifts.index', 'business-time'],
        ['Attendance', 'attendance', 'attendances.index', 'clipboard-check'],
        ['Holiday', 'holiday', 'holidays.index', 'umbrella-beach'],
        ['Designation', 'designation', 'designations.index', 'user-tag'],
        ['Department', 'department', 'departments.index', 'sitemap'],
        ['Work', 'work', 'projects.index', 'briefcase'],
        ['Finance', 'finance', 'invoices.index', 'coins'],
        ['Products', 'products', 'products.index', 'box-open'],
        ['Orders', 'orders', 'orders.index', 'shopping-cart'],
        ['Tickets', 'tickets', 'tickets.index', 'headset'],
        ['Events', 'events', 'events.index', 'calendar-day'],
        ['Messages', 'messages', 'messages.index', 'comment-dots'],
        ['Notice Board', 'notice-board', 'notices.index', 'thumbtack'],
        ['Knowledge Base', 'knowledgebase', 'knowledgebase.index', 'book'],
        ['Reports', 'reports', 'task-report.index', 'chart-pie'],
        ['Settings', 'settings', 'company-settings.index', 'cog'],
    ];

    /** The palette the view cycled through, kept so colours do not change. */
    protected array $palette = [
        '#0d9488', '#7c3aed', '#dc2626', '#16a34a', '#2563eb', '#d97706', '#db2777',
        '#0891b2', '#65a30d', '#9333ea', '#ea580c', '#0f766e', '#4f46e5', '#be123c',
    ];

    public function run(): void
    {
        $companyId = DB::table('companies')->orderBy('id')->value('id');
        $order = 0;

        foreach ($this->tiles as $i => [$name, $slug, $route, $icon]) {
            $attributes = [
                'company_id' => $companyId,
                'name' => $name,
                'route' => $route,
                'icon' => 'fas fa-' . $icon,
                'color' => $this->palette[$i % count($this->palette)],
                'sort_order' => ++$order,
                'target' => '_self',
            ];

            $existing = AppLauncher::where('company_id', $companyId)->where('slug', $slug)->first();

            if ($existing) {
                // is_active is left alone, same reasoning as the menu seeder:
                // an administrator may have switched a tile off.
                $existing->fill($attributes)->save();
                continue;
            }

            AppLauncher::create($attributes + ['slug' => $slug, 'is_active' => 1]);
        }

        cache()->forget('app_launchers_all');

        $this->command?->info(sprintf('Seeded %d launcher tiles.', AppLauncher::count()));
    }

}
