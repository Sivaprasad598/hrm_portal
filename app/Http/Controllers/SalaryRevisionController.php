<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Services\Payroll\SalaryRevisionGrid;
use App\Services\Payroll\SalaryRevisionImport;
use App\Services\Payroll\SalaryRevisionLetter;
use App\Services\Payroll\SalaryRevisions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * HR -> Salary Revisions: hikes, promotions and appraisal cycles. HR prepares,
 * the CFO and then the CEO approve; the letters follow and payroll pays the new
 * salary from the effective month (SalaryRevisions).
 */
class SalaryRevisionController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'Salary Revisions';

        $this->middleware(function ($request, $next) {
            // An employee may open their own released letter; everything else is for HR / Admin / CFO / CEO.
            if (!$request->routeIs('salary-revisions.letter')) {
                abort_403(!SalaryRevisions::canView());
            }

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        (new SalaryRevisions())->applyDue();
        $this->status = $request->status;
        $levels = SalaryRevisions::approverLevels();
        $awaiting = array_map(fn ($l) => 'pending_' . $l, $levels);

        $this->batches = DB::table('salary_revision_batches as b')->leftJoin('users as c', 'c.id', '=', 'b.created_by')
            ->when($this->status === 'mine', fn ($q) => $q->whereIn('b.status', $awaiting ?: ['-']))
            ->when($this->status && $this->status !== 'mine', fn ($q) => $q->where('b.status', $this->status))
            ->orderByRaw("FIELD(b.status, 'pending_cfo', 'pending_ceo', 'rejected', 'draft', 'approved')")->orderByDesc('b.id')
            ->get(['b.*', 'c.name as created_name']);

        $sums = DB::table('salary_revisions')->whereIn('batch_id', $this->batches->pluck('id'))->groupBy('batch_id')
            ->selectRaw('batch_id, COUNT(*) AS people, SUM(old_ctc) AS old_total, SUM(new_ctc) AS new_total')->get()->keyBy('batch_id');
        $this->sums = $sums;
        $this->awaitingCount = DB::table('salary_revision_batches')->whereIn('status', $awaiting ?: ['-'])->count();

        // This financial year, approved.
        $fyStart = now()->month >= 4 ? Carbon::create(now()->year, 4, 1) : Carbon::create(now()->year - 1, 4, 1);
        $fy = DB::table('salary_revisions as r')->join('salary_revision_batches as b', 'b.id', '=', 'r.batch_id')
            ->where('b.status', 'approved')->where('b.ceo_at', '>=', $fyStart)
            ->selectRaw('COUNT(*) AS people, SUM(r.old_ctc) AS old_total, SUM(r.new_ctc) AS new_total, SUM(CASE WHEN r.new_designation_id IS NOT NULL THEN 1 ELSE 0 END) AS promoted')->first();
        $this->fy = $fy;
        $this->fyLabel = $fyStart->year . '-' . substr($fyStart->year + 1, 2);
        $this->canPrepare = SalaryRevisions::canPrepare();
        $this->levels = $levels;

        return view('salary-revisions.index', $this->data);
    }

    public function create(Request $request)
    {
        abort_403(!SalaryRevisions::canPrepare());
        $this->batch = null;
        $single = $request->user_id ? (int) $request->user_id : null;
        $this->rows = $single ? (new SalaryRevisionGrid())->rows([$single]) : [];
        $this->kind = $single ? 'single' : 'cycle';
        $this->defaultName = $single ? 'Salary revision – ' . ($this->rows[0]['name'] ?? '') : 'Annual Appraisal ' . (now()->month >= 4 ? now()->year . '-' . substr(now()->year + 1, 2) : (now()->year - 1) . '-' . substr(now()->year, 2));

        return $this->form();
    }

    public function edit($id)
    {
        abort_403(!SalaryRevisions::canPrepare());
        $this->batch = DB::table('salary_revision_batches')->find($id);
        abort_if(!$this->batch, 404);
        abort_if(!in_array($this->batch->status, ['draft', 'rejected']), 403, 'Only a draft, or one sent back, can be changed.');
        $this->rows = (new SalaryRevisionGrid(Carbon::parse($this->batch->applies_from)))->fromBatch((int) $id);
        $this->kind = $this->batch->kind;
        $this->defaultName = $this->batch->name;

        return $this->form();
    }

    protected function form()
    {
        $this->employees = DB::table('users as u')->join('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->where('u.status', 'active')->orderBy('u.name')->get(['u.id', 'u.name', 'ed.employee_id', 'u.employee_ctc']);
        $this->designations = DB::table('designations')->orderBy('name')->pluck('name', 'id');
        $this->departments = DB::table('teams')->orderBy('team_name')->pluck('team_name', 'id');
        $this->types = SalaryRevisions::TYPES;

        return view('salary-revisions.form', $this->data);
    }

    /** Employees to add to the form, as rows. */
    public function rows(Request $request)
    {
        abort_403(!SalaryRevisions::canPrepare());
        $appliesFrom = SalaryRevisions::appliesFrom($request->effective_date ? Carbon::parse($request->effective_date) : now()->addDay());
        $ids = (array) $request->user_ids;

        if ($request->department) {
            $ids = array_merge($ids, DB::table('employee_details as ed')->join('users as u', 'u.id', '=', 'ed.user_id')
                ->where('u.status', 'active')->where('u.employee_ctc', '>', 0)
                ->when($request->department !== 'all', fn ($q) => $q->where('ed.department_id', $request->department))
                ->pluck('u.id')->all());
        }

        return Reply::dataOnly(['status' => 'success', 'rows' => (new SalaryRevisionGrid($appliesFrom))->rows($ids),
            'applies' => SalaryRevisions::monthLabel($appliesFrom)]);
    }

    /** What the effective date means for payroll. */
    public function applies(Request $request)
    {
        $request->validate(['effective_date' => 'required|date']);
        $from = SalaryRevisions::appliesFrom(Carbon::parse($request->effective_date));
        $cycle = \App\Services\Payroll\PayPeriod::containing($from);

        return Reply::dataOnly(['status' => 'success', 'month' => SalaryRevisions::monthLabel($from),
            'period' => $cycle->start->format('d M Y') . ' – ' . $cycle->end->format('d M Y')]);
    }

    public function store(Request $request, SalaryRevisions $service)
    {
        abort_403(!SalaryRevisions::canPrepare());
        $request->validate([
            'name' => 'required|string|max:150', 'effective_date' => 'required|date', 'kind' => 'in:single,cycle',
            'rows' => 'required|array|min:1', 'rows.*.user_id' => 'required|integer|exists:users,id', 'rows.*.new_ctc' => 'required|numeric|min:1',
            'rows.*.new_basic_pct' => 'nullable|numeric|min:1|max:100',
        ], ['rows.required' => 'Add at least one employee.', 'rows.*.new_ctc.required' => 'Every employee needs a new CTC.']);

        try {
            $id = $service->save($request->id ? (int) $request->id : null, (int) company()->id, $request->only('name', 'effective_date', 'kind', 'note'), $request->rows);

            if ($request->boolean('submit')) {
                $service->submit($id);
            }
        } catch (RuntimeException $e) {
            return Reply::error($e->getMessage());
        }

        return Reply::redirect(route('salary-revisions.show', $id), $request->boolean('submit') ? 'Sent to the CFO for approval.' : 'Saved as a draft.');
    }

    public function show($id)
    {
        (new SalaryRevisions())->applyDue();
        $this->batch = DB::table('salary_revision_batches as b')->leftJoin('users as c', 'c.id', '=', 'b.created_by')
            ->leftJoin('users as f', 'f.id', '=', 'b.cfo_by')->leftJoin('users as e', 'e.id', '=', 'b.ceo_by')->leftJoin('users as x', 'x.id', '=', 'b.rejected_by')
            ->where('b.id', $id)->first(['b.*', 'c.name as created_name', 'f.name as cfo_name', 'e.name as ceo_name', 'x.name as rejected_name']);
        abort_if(!$this->batch, 404);

        $this->revisions = DB::table('salary_revisions as r')->join('users as u', 'u.id', '=', 'r.user_id')->join('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->leftJoin('designations as od', 'od.id', '=', 'r.old_designation_id')->leftJoin('designations as nd', 'nd.id', '=', 'r.new_designation_id')
            ->leftJoin('teams as ot', 'ot.id', '=', 'r.old_department_id')->leftJoin('teams as nt', 'nt.id', '=', 'r.new_department_id')
            ->where('r.batch_id', $id)->orderBy('u.name')
            ->get(['r.*', 'u.name', 'ed.employee_id as code', 'od.name as old_designation', 'nd.name as new_designation', 'ot.team_name as old_department', 'nt.team_name as new_department']);
        $this->logs = DB::table('salary_revision_logs as l')->leftJoin('users as u', 'u.id', '=', 'l.user_id')->where('l.batch_id', $id)
            ->orderBy('l.id')->get(['l.*', 'u.name as user_name']);
        $this->canPrepare = SalaryRevisions::canPrepare();
        $this->levels = SalaryRevisions::approverLevels();
        $this->myLevel = ['pending_cfo' => 'cfo', 'pending_ceo' => 'ceo'][$this->batch->status] ?? null;
        $this->canAct = $this->myLevel && in_array($this->myLevel, $this->levels)
            && ((int) $this->batch->created_by !== (int) user()->id || in_array('admin', user_roles()));
        $this->payMonth = SalaryRevisions::monthLabel(Carbon::parse($this->batch->applies_from));

        return view('salary-revisions.show', $this->data);
    }

    public function action(Request $request, $id, SalaryRevisions $service)
    {
        $request->validate(['do' => 'required|in:submit,withdraw,approve,reject,release,delete']);

        try {
            switch ($request->do) {
                case 'submit':
                case 'withdraw':
                case 'release':
                case 'delete':
                    abort_403(!SalaryRevisions::canPrepare());
                    $service->{$request->do === 'delete' ? 'delete' : $request->do}((int) $id);
                    $message = ['submit' => 'Sent to the CFO for approval.', 'withdraw' => 'Withdrawn; it is a draft again.',
                        'release' => 'Letters released: each employee can now see theirs on their profile.', 'delete' => 'Deleted.'][$request->do];
                    break;
                case 'approve':
                    $message = $service->approve((int) $id, $request->note);
                    break;
                default:
                    $service->reject((int) $id, (string) $request->note);
                    $message = 'Sent back to HR.';
            }
        } catch (RuntimeException $e) {
            return Reply::error($e->getMessage());
        }

        return Reply::redirect($request->do === 'delete' ? route('salary-revisions.index') : route('salary-revisions.show', $id), $message);
    }

    /** One employee's letter (theirs, once released), or all of a batch. */
    public function letter(Request $request, $id, SalaryRevisionLetter $letter)
    {
        $revision = DB::table('salary_revisions as r')->join('salary_revision_batches as b', 'b.id', '=', 'r.batch_id')
            ->where('r.id', $id)->first(['r.id', 'r.user_id', 'r.letter_no', 'b.status', 'b.released_at']);
        abort_if(!$revision, 404);
        $own = (int) $revision->user_id === (int) user()->id && $revision->status === 'approved' && $revision->released_at;
        abort_403(!$own && !SalaryRevisions::canView());

        $name = DB::table('users')->where('id', $revision->user_id)->value('name');

        return $letter->pdf([(int) $id])->stream('Salary-revision-' . \Illuminate\Support\Str::slug($name) . '.pdf');
    }

    public function letters($id, SalaryRevisionLetter $letter)
    {
        $ids = DB::table('salary_revisions as r')->join('users as u', 'u.id', '=', 'r.user_id')->where('r.batch_id', $id)->orderBy('u.name')->pluck('r.id')->all();
        abort_if(!$ids, 404);
        @ini_set('memory_limit', '1024M');
        @set_time_limit(300);

        return $letter->pdf($ids)->stream('Salary-revision-letters-' . $id . '.pdf');
    }

    public function template(Request $request, SalaryRevisionImport $import)
    {
        abort_403(!SalaryRevisions::canPrepare());

        return response()->download($import->template($request->department ? (int) $request->department : null), 'salary-revisions-template.xlsx')->deleteFileAfterSend();
    }

    public function import(Request $request, SalaryRevisionImport $import)
    {
        abort_403(!SalaryRevisions::canPrepare());
        $request->validate(['import_file' => 'required|file|mimes:xlsx,xls,csv|max:10240'], ['import_file.required' => 'Choose the spreadsheet.']);
        $result = $import->read($request->file('import_file')->getRealPath());

        if (!$result['rows'] && $result['errors']) {
            return Reply::error(implode(' ', array_slice($result['errors'], 0, 5)));
        }

        return Reply::dataOnly(['status' => 'success', 'rows' => $result['rows'], 'errors' => $result['errors']]);
    }

    public function settings(Request $request, SalaryRevisions $service)
    {
        abort_403(!SalaryRevisions::canPrepare());

        if ($request->isMethod('get')) {
            return Reply::dataOnly(['status' => 'success', 'settings' => $service->settings()]);
        }

        $data = $request->validate(['letter_prefix' => 'required|string|max:40', 'signatory_name' => 'nullable|string|max:120',
            'signatory_designation' => 'nullable|string|max:120', 'hr_contact' => 'nullable|string|max:191']);
        $service->settings();
        DB::table('salary_revision_settings')->where('company_id', company()->id)->update($data + ['updated_at' => now()]);

        return Reply::success('Letter settings saved.');
    }

}
