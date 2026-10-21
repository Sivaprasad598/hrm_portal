<?php

namespace App\Http\Controllers;

use App\DataTables\AssetsDataTable;
use App\Helper\Files;
use App\Helper\Reply;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetType;
use App\Models\Branch;
use App\Models\User;
use App\Scopes\ActiveScope;
use App\Services\Assets\AssetFlow;
use App\Services\Assets\AssetImport;
use App\Services\Assets\AssetUpkeep;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Assets: the register, handing an asset to an employee and taking it back.
 * Everything here needs manage_assets (Super Admin, HR, Admin, CEO).
 */
class AssetController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'Assets';

        $this->middleware(function ($request, $next) {
            abort_403(user()->permission('manage_assets') !== 'all');

            return $next($request);
        });
    }

    public function index(AssetsDataTable $dataTable)
    {
        $this->types = AssetType::orderBy('name')->get();
        $this->branches = Branch::orderBy('branch_name')->get();
        $this->counts = Asset::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $this->overdue = AssetAssignment::whereNull('returned_on')->whereDate('expected_return_on', '<', now()->toDateString())->count();
        $this->warrantySoon = Asset::whereNotIn('status', ['lost', 'disposed'])
            ->whereBetween('warranty_until', [now()->toDateString(), now()->addDays(30)->toDateString()])->count();
        $this->dueOverdue = Asset::whereNotNull('next_due_on')->whereDate('next_due_on', '<', now()->toDateString())->count();
        $this->dueSoon = Asset::whereNotNull('next_due_on')
            ->whereBetween('next_due_on', [now()->toDateString(), now()->addDays(Asset::DUE_SOON_DAYS)->toDateString()])->count();
        $this->holders = $this->activeEmployees(true)
            ->whereIn('id', AssetAssignment::whereNull('returned_on')->pluck('user_id')->unique()->all())->values();

        return $dataTable->render('assets.index', $this->data);
    }

    public function create()
    {
        $this->asset = new Asset(['asset_code' => Asset::nextCode(), 'condition' => 'new', 'branch_id' => Branch::value('id')]);

        return $this->panel('assets.ajax.form', 'Add Asset');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['company_id'] = company()->id;
        $data['status'] = 'available';
        $data['created_by'] = user()->id;

        $asset = Asset::create($data);
        app(AssetFlow::class)->log($asset, 'created', null, null, 'Added to the register');
        app(AssetUpkeep::class)->refreshDue($asset);

        return Reply::successWithData('Asset added.', ['redirectUrl' => route('assets.index'), 'assetId' => $asset->id]);
    }

    public function show($id)
    {
        $this->asset = Asset::with(['type', 'branch', 'currentAssignment.user', 'assignments.user', 'histories.user', 'histories.doneBy',
            'services', 'renewals.next'])->findOrFail($id);

        return $this->panel('assets.ajax.show', $this->asset->name);
    }

    public function edit($id)
    {
        $this->asset = Asset::findOrFail($id);

        return $this->panel('assets.ajax.form', 'Edit Asset — ' . $this->asset->name);
    }

    public function update(Request $request, $id)
    {
        $asset = Asset::findOrFail($id);
        $asset->update($this->validated($request, $asset));
        app(AssetFlow::class)->log($asset, 'updated', null, null, 'Details edited');
        app(AssetUpkeep::class)->refreshDue($asset);

        return Reply::successWithData('Asset updated.', ['redirectUrl' => route('assets.index')]);
    }

    public function destroy($id)
    {
        $asset = Asset::withCount('assignments')->findOrFail($id);

        if ($asset->assignments_count) {
            return Reply::error('This asset has been assigned before, so its history is kept. Mark it Disposed instead.');
        }

        if ($asset->image) {
            Files::deleteFile($asset->image, Asset::FILE_PATH);
        }

        $asset->delete();

        return Reply::success('Asset deleted.');
    }

    /** Hand-over form; opened from an asset (asset_id) or from an employee (user_id). */
    public function assignForm(Request $request)
    {
        $this->asset = $request->asset_id ? Asset::findOrFail($request->asset_id) : null;
        $this->employeeId = (int) $request->user_id;
        $this->availableAssets = Asset::with('type')->where('status', 'available')->orderBy('name')->get();
        $this->employees = $this->activeEmployees();

        return $this->panel('assets.ajax.assign', 'Assign Asset');
    }

    public function assign(Request $request, AssetFlow $flow)
    {
        $request->validate([
            'asset_id' => 'required|integer',
            'user_id' => 'required|integer',
            'assigned_on' => 'required',
            'condition' => ['required', Rule::in(array_keys(Asset::CONDITIONS))],
        ], ['asset_id.required' => 'Choose the asset.', 'user_id.required' => 'Choose the employee.']);

        $asset = Asset::findOrFail($request->asset_id);
        $employee = User::withoutGlobalScope(ActiveScope::class)->findOrFail($request->user_id);

        try {
            $flow->assign($asset, $employee, [
                'assigned_on' => $this->date($request->assigned_on, 'assigned date'),
                'expected_return_on' => $request->expected_return_on ? $this->date($request->expected_return_on, 'return date') : null,
                'condition' => $request->condition,
                'notes' => $request->notes,
            ]);
        } catch (RuntimeException $e) {
            return Reply::error($e->getMessage());
        }

        return Reply::success($asset->name . ' assigned to ' . $employee->name . '.');
    }

    public function returnForm($assignmentId)
    {
        $this->assignment = AssetAssignment::with('asset', 'user')->whereNull('returned_on')->findOrFail($assignmentId);

        return $this->panel('assets.ajax.return', 'Return Asset');
    }

    public function returnAsset(Request $request, $assignmentId, AssetFlow $flow)
    {
        $request->validate([
            'returned_on' => 'required',
            'back_to' => 'required|in:available,repair,lost',
            'condition' => ['required_unless:back_to,lost', 'nullable', Rule::in(array_keys(Asset::CONDITIONS))],
            'recovery_amount' => 'nullable|numeric|min:0',
        ]);

        $assignment = AssetAssignment::with('asset', 'user')->findOrFail($assignmentId);
        $returnedOn = $this->date($request->returned_on, 'return date');

        if ($returnedOn < $assignment->assigned_on->toDateString()) {
            return Reply::error('The return date cannot be before the asset was assigned (' . $assignment->assigned_on->format(company()->date_format) . ').');
        }

        try {
            $flow->return($assignment, [
                'returned_on' => $returnedOn,
                'condition' => $request->condition,
                'back_to' => $request->back_to,
                'notes' => $request->notes,
                'recovery_amount' => $request->recovery_amount,
            ]);
        } catch (RuntimeException $e) {
            return Reply::error($e->getMessage());
        }

        return Reply::success($assignment->asset->name . ($request->back_to === 'lost' ? ' recorded as lost.' : ' returned.'));
    }

    public function statusForm($id)
    {
        $this->asset = Asset::findOrFail($id);
        $this->choices = collect(AssetFlow::MANUAL)->filter(fn ($from) => in_array($this->asset->status, $from, true))->keys();

        return $this->panel('assets.ajax.status', 'Change Status');
    }

    public function status(Request $request, $id, AssetFlow $flow)
    {
        $request->validate([
            'status' => 'required|in:' . implode(',', array_keys(AssetFlow::MANUAL)),
            'cost' => 'nullable|numeric|min:0',
            'vendor' => 'nullable|string|max:191',
        ]);

        $asset = Asset::findOrFail($id);

        try {
            $flow->changeStatus($asset, $request->status, $request->notes, [
                'vendor' => $request->vendor,
                'sent_on' => $request->sent_on ? $this->date($request->sent_on, 'sent date') : null,
                'completed_on' => $request->completed_on ? $this->date($request->completed_on, 'completed date') : null,
                'cost' => $request->cost,
            ]);
        } catch (RuntimeException $e) {
            return Reply::error($e->getMessage());
        }

        return Reply::success($asset->name . ' is now ' . strtolower(Asset::STATUSES[$request->status][0]) . '.');
    }

    /** Bulk import: the upload panel. */
    public function importForm()
    {
        return $this->panel('assets.ajax.import', 'Import Assets');
    }

    public function importTemplate(AssetImport $import)
    {
        return response()->download($import->template(), 'asset-import-template.xlsx')->deleteFileAfterSend();
    }

    /** Read the sheet and show every row with what will happen to it. Nothing is saved yet. */
    public function importPreview(Request $request, AssetImport $import)
    {
        $request->validate(['import_file' => 'required|file|mimes:xlsx,xls,csv|max:10240'], ['import_file.required' => 'Choose the spreadsheet to import.']);

        $this->preview = $import->preview($request->file('import_file')->getRealPath());

        if ($this->preview['error']) {
            return Reply::error($this->preview['error']);
        }

        $this->token = Str::random(32);
        cache()->put('asset_import_' . user()->id . '_' . $this->token, $this->preview['rows'], now()->addHour());

        return Reply::dataOnly(['status' => 'success', 'html' => view('assets.ajax.import-review', $this->data)->render()]);
    }

    public function importRun(Request $request, AssetImport $import)
    {
        $key = 'asset_import_' . user()->id . '_' . $request->token;
        $rows = cache()->get($key);

        if (!$rows) {
            return Reply::error('This preview has expired. Upload the file again.');
        }

        $result = $import->apply($rows, (bool) $request->notify, (int) company()->id);
        cache()->forget($key);

        return Reply::successWithData(
            $result['created'] . ' asset(s) imported' . ($result['assigned'] ? ', ' . $result['assigned'] . ' assigned to employees' : '')
            . ($result['types'] ? ', ' . $result['types'] . ' new type(s)' : '') . '.',
            ['redirectUrl' => route('assets.index')]
        );
    }

    /**
     * Printable acknowledgement for the employee to sign: one hand-over
     * (?assignment=) or everything they hold now (?user=).
     */
    public function handover(Request $request)
    {
        if ($request->assignment) {
            $this->items = AssetAssignment::with('asset.type', 'assignedBy')->where('id', $request->assignment)->get();
            abort_if($this->items->isEmpty(), 404);
            $userId = $this->items->first()->user_id;
        } else {
            $userId = (int) $request->user;
            $this->items = AssetAssignment::with('asset.type', 'assignedBy')->where('user_id', $userId)
                ->whereNull('returned_on')->orderBy('assigned_on')->get();
        }

        $this->employee = User::withoutGlobalScope(ActiveScope::class)
            ->with('employeeDetail.designation', 'employeeDetail.department')->findOrFail($userId);
        $this->company = company();

        return view('assets.handover', $this->data);
    }

    /** Who holds what right now. */
    public function assigned(Request $request)
    {
        $this->pageTitle = 'Assigned Assets';
        $this->employees = $this->activeEmployees(true);
        $this->types = AssetType::orderBy('name')->get();

        $this->assignments = AssetAssignment::with(['asset.type', 'user.employeeDetail'])
            ->whereNull('returned_on')
            ->when($request->user_id, fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->type_id, fn ($q, $id) => $q->whereHas('asset', fn ($a) => $a->where('asset_type_id', $id)))
            ->when($request->overdue, fn ($q) => $q->whereDate('expected_return_on', '<', now()->toDateString()))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w
                ->whereHas('asset', fn ($a) => $a->where('name', 'like', "%$s%")->orWhere('asset_code', 'like', "%$s%")->orWhere('serial_number', 'like', "%$s%"))
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%$s%"))))
            ->orderBy('assigned_on', 'desc')
            ->paginate(50)
            ->withQueryString();

        return view('assets.assigned', $this->data);
    }

    private function validated(Request $request, ?Asset $asset = null): array
    {
        $request->validate([
            'name' => 'required|string|max:191',
            'asset_code' => ['required', 'string', 'max:50',
                Rule::unique('assets', 'asset_code')->where('company_id', company()->id)->ignore($asset?->id)],
            'asset_type_id' => 'nullable|integer',
            'serial_number' => 'nullable|string|max:191',
            'brand' => 'nullable|string|max:191',
            'model' => 'nullable|string|max:191',
            'purchase_cost' => 'nullable|numeric|min:0',
            'vendor' => 'nullable|string|max:191',
            'branch_id' => 'nullable|integer',
            'condition' => ['required', Rule::in(array_keys(Asset::CONDITIONS))],
            'image' => 'nullable|image|max:4096',
            'service_interval_months' => 'nullable|integer|min:1|max:120',
        ], ['asset_code.unique' => 'Another asset already has this code.']);

        $data = $request->only(['name', 'asset_code', 'serial_number', 'brand', 'model', 'vendor', 'condition', 'notes']);
        $data['asset_type_id'] = $request->asset_type_id ?: null;
        $data['branch_id'] = $request->branch_id ?: null;
        $data['purchase_cost'] = $request->purchase_cost !== null && $request->purchase_cost !== '' ? $request->purchase_cost : null;
        $data['purchase_date'] = $request->purchase_date ? $this->date($request->purchase_date, 'purchase date') : null;
        $data['warranty_until'] = $request->warranty_until ? $this->date($request->warranty_until, 'warranty date') : null;
        $data['service_interval_months'] = $request->service_interval_months ?: null;
        $data['next_service_on'] = $request->next_service_on ? $this->date($request->next_service_on, 'next service date') : null;

        if ($request->hasFile('image')) {
            if ($asset?->image) {
                Files::deleteFile($asset->image, Asset::FILE_PATH);
            }

            $data['image'] = Files::uploadLocalOrS3($request->image, Asset::FILE_PATH, 600);
        } elseif ($request->image_delete === 'yes' && $asset?->image) {
            Files::deleteFile($asset->image, Asset::FILE_PATH);
            $data['image'] = null;
        }

        return $data;
    }

    /** A date typed in the company format, as Y-m-d. */
    private function date(string $value, string $label): string
    {
        try {
            return Carbon::createFromFormat(company()->date_format, $value)->toDateString();
        } catch (\Throwable $e) {
            abort(response()->json(['status' => 'fail', 'message' => 'The ' . $label . ' is not a valid date.', 'errors' => []], 422));
        }
    }

    private function activeEmployees(bool $withHolders = false)
    {
        // Anybody still holding an asset is listed too, even after leaving.
        return User::withoutGlobalScope(ActiveScope::class)
            ->join('employee_details', 'employee_details.user_id', '=', 'users.id')
            ->where(fn ($q) => $q->where('users.status', 'active')
                ->when($withHolders, fn ($w) => $w->orWhereIn('users.id', AssetAssignment::whereNull('returned_on')->select('user_id'))))
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'employee_details.employee_id']);
    }

    private function panel(string $view, string $title)
    {
        $this->pageTitle = $title;
        $this->view = $view;

        if (request()->ajax()) {
            return Reply::dataOnly(['status' => 'success', 'html' => view($view, $this->data)->render(), 'title' => $title]);
        }

        return view('assets.page', $this->data);
    }

}
