# Branches and modules

Split done on 2026-11-14. **`hrm_v1_core` is the working branch.** Every other module lives on its own branch
and sits in one folder, `app-modules/<Name>/`. All branches use the same database. No tables or
migrations were removed.

- **Turn a module on:** while on `hrm_v1_core`, run `git merge hrm_v1_module_<name>`, then `php artisan optimize:clear`.
  The merge adds only that module's folder.
- **Turn a module off:** remove its `app-modules/<Name>/` folder (or don't merge it). Its menu items, settings links,
  dashboard tabs and buttons hide, and its URLs return 404.
- **Safety copies, untouched:** `main` and `hrm_v1_ui_redesign` (the code before the split).

---

## hrm_v1_core (the product)

| Sidebar module | Sub-menus |
|---|---|
| Dashboard | Private Dashboard, Advanced Dashboard (Overview, Project, HR tabs) |
| My Space | My Leaves, My Attendance, My Payslips, My Resignation, My Assets, My Overtime, My Interviews, My Calendar |
| My Team (managers) | Team Leaves, Team Attendance |
| HR | Employees, Attendance Register, Attendance Dashboard, Exited Employees, Salary Revisions, Employee Import |
| Recruitment | Job Dashboard, Positions, Applicants, Resumes, Interviews |
| Leave Management | Leave Requests, Leave Balances, Year-End Settlement, Holidays |
| Payroll | Payroll Dashboard, Monthly Payroll, Pay Elements & OT, Tax & Returns, Final Settlement |
| Assets | Asset List, Assigned Assets, Asset Types |
| Biometric | Overview, Daily Review, Exceptions, Punch Log, Employee 360 (Employee Mapping tab) |
| Work | Contracts, Projects, Tasks, Timesheet, Weekly Timesheets, Task Board, Weekly Timesheet (report) |
| Settings | Company, App, App Launcher, Attendance, Biometric, Data Clean, Business Address, AI, Recruitment, Contract, Currency, Custom Fields, Custom Links, Database Backup, Downloads, Finance (invoice) Settings, Google Calendar, Language, Leaves, Module, Notice Period, Notifications, Payment Credentials, Payslip, Project, Access, Security, Sign Up, Social Login, Storage, Task, Tax, Theme, Time Log, Shifts, Roles & Permissions, Mail (SMTP) |
| Add-ons | All add-ons stay in core (Zoom etc.) |

**Shared pieces kept in core**, because core or several modules use them:
- the Event models (Recruitment interviews, task meetings and My Calendar use them);
- the client and finance data models (projects keep their client);
- the client lookups behind the invoice, contract and project forms (`ClientLookupController`);
- the public invoice, estimate and payment pages, the payment gateways, and the invoice and estimate PDF templates;
- the ticket dashboard trait, and the `lead_setting` model (it also holds the ticket round-robin flag);
- the weekly timesheet report tables.

---

## Module branches

| Branch | Folder | Sidebar / sub-menus | Settings page | Scheduled jobs | Needs |
|---|---|---|---|---|---|
| `hrm_v1_module_knowledge-base` | KnowledgeBase | Knowledge Base (articles, categories, files) | - | - | - |
| `hrm_v1_module_notice-board` | NoticeBoard | Notice Board (notices, files, read status) | - | - | - |
| `hrm_v1_module_notes` | ClientNotes | Notes tab on the client page (client notes) | - | - | Clients |
| `hrm_v1_module_gdpr` | Gdpr | GDPR (client/lead consent, data removal requests, public consent pages) | GDPR Settings | - | Leads, for the lead removal tab only |
| `hrm_v1_module_messages` | Messages | Messages (chat, files) | Message Settings | - | - |
| `hrm_v1_module_events` | Events | Events calendar, Recurring events, event files | - | - | - |
| `hrm_v1_module_reports` | Reports | Task, Time Log, Finance, Income vs Expense, Leave (quota), Attendance, Expense, Deal, Sales reports | - | - | Leads, for the Deal report only |
| `hrm_v1_module_tickets` | Tickets | Tickets (tickets, replies, files, agents, groups, channels, types, reply templates, custom form, email settings); Ticket dashboard tab; public ticket form | Ticket Settings | fetch-ticket-emails (command) | - |
| `hrm_v1_module_leads` | Leads | Leads: Lead Contacts, Deals (lead board, notes, files, follow-ups, pipelines, stages, sources, categories, agents, custom form); **Proposal** and Proposal Templates (shown under the Finance menu); public lead form and proposal pages | Lead Settings | send-auto-followup-reminder (every minute) | - |
| `hrm_v1_module_clients` | Clients | Clients (list, profile, contacts, documents, categories, sub-categories, import); Client Dashboard tab | - | - | Finance, for the invoice/estimate/payment/credit note/order tabs on a client |
| `hrm_v1_module_finance` | Finance | Finance: Estimates, Estimate Requests, Estimate Templates, Invoices, Recurring Invoices, Payments, Credit Notes, Expenses, Recurring Expenses, Bank Accounts, Products (categories, sub-categories, files), Orders, Subscriptions, My Subscriptions; Finance dashboard tab; finance tabs on projects | - (Finance, Tax, Currency and Payment settings stay in core) | recurring-invoice-create (01:30), recurring-expenses-create (01:40), send-invoice-reminder (hourly) | - |

### Merge combinations to know
- **Notes** is only reachable from the client page, so merge it together with **Clients**.
- **Clients + Finance** together show the finance tabs on a client.
- **Leads without Finance:** the Finance menu heading still shows, holding only Proposal. The convert-to-invoice
  and add-product buttons go to the dashboard.
- **Reports without Leads:** the Deal report hides. **GDPR without Leads:** the lead removal tab hides.

---

## Rules for new code on core
- Code that touches a split module must check it is there first:
  - in Blade, `Route::has('<route>')`;
  - in PHP, `class_exists(\AppModules\<Name>\...)`;
  - for links, `\App\Support\ModulePresence::url()` or `href()`.
- Never name a moved class by its old `App\...` name. The old composer class map still points those names at deleted files.
- Stored class names stay `App\Models\X`: each module's morph map translates them. Keep it that way, so data stays the
  same in every branch.
- Every database change goes in a migration.
