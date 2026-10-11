<?php

namespace App\Http\Controllers;

use App\Helper\Files;
use App\Helper\Reply;
use App\Http\Requests\EmployeeDocumentExpiry\CreateRequest;
use App\Http\Requests\EmployeeDocumentExpiry\UpdateRequest;
use App\Models\EmployeeDocumentExpiry;
use App\Models\User;
use Carbon\Carbon;

class EmployeeDocumentExpiryController extends AccountBaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'app.menu.employeeDocumentExpiry';
        $this->middleware(function ($request, $next) {
            abort_403(!in_array('employees', $this->user->modules));

            return $next($request);
        });
    }

    public function create()
    {
        $addPermission = user()->permission('add_documents');

        abort_403(!($addPermission == 'all'));

        // Get the employee ID from the route parameter
        $employeeId = request()->user_id;
        $this->user = User::findOrFail($employeeId);

        return view('employees.ajax.document-expiry.create', $this->data);
    }

    public function store(CreateRequest $request)
    {
        $fileFormats = explode(',', global_setting()->allowed_file_types);

        if ($request->hasFile('file')) {
            // if (!in_array($request->file->getClientMimeType(), $fileFormats)) {
            //     return Reply::error(__('messages.employeeDocsAllowedFormat'));
            // }
        }
        
        try {
            $doc_issue_date = Carbon::createFromFormat(company()->date_format, $request->issue_date)->format('Y-m-d');
            $doc_expiry_date = Carbon::createFromFormat(company()->date_format, $request->expiry_date)->format('Y-m-d');
        } catch (\Exception $e) {
            // Fallback to Carbon::parse if format doesn't match
            $doc_issue_date = Carbon::parse($request->issue_date)->format('Y-m-d');
            $doc_expiry_date = Carbon::parse($request->expiry_date)->format('Y-m-d');
        }
            
        // info([$doc_issue_date, $doc_expiry_date]);

        $document = new EmployeeDocumentExpiry();
        $document->user_id = $request->user_id;
        $document->company_id = company()->id;
        $document->document_name = $request->document_name;
        $document->document_number = $request->document_number ?? '--';
        $document->issue_date = $doc_issue_date;
        $document->expiry_date = $doc_expiry_date;
        $document->alert_before_days = $request->alert_before_days;
        $document->alert_enabled = $request->alert_enabled;
        $document->added_by = user()->id;

        if ($request->hasFile('file')) {
            $filename = Files::uploadLocalOrS3($request->file, EmployeeDocumentExpiry::FILE_PATH . '/' . $request->user_id);
            $document->filename = $request->file->getClientOriginalName();
            $document->hashname = $filename;
            $document->size = $request->file->getSize();
        }

        $document->save();

        $this->documents = EmployeeDocumentExpiry::where('user_id', $request->user_id)
            ->orderByDesc('id')
            ->get();

        $view = view('employees.ajax.document-expiry.show', $this->data)->render();

        return Reply::successWithData(__('messages.recordSaved'), ['status' => 'success', 'view' => $view]);
    }

    public function edit($id)
    {
        $this->document = EmployeeDocumentExpiry::findOrFail($id);
        $editPermission = user()->permission('edit_documents');

        abort_403(!($editPermission == 'all'
            || ($editPermission == 'added' && $this->document->added_by == user()->id)
            || ($editPermission == 'owned' && ($this->document->user_id == user()->id && $this->document->added_by != user()->id))
            || ($editPermission == 'both' && ($this->document->added_by == user()->id || $this->document->user_id == user()->id))));

        return view('employees.ajax.document-expiry.edit', $this->data);
    }

    public function update(UpdateRequest $request, $id)
    {
        $document = EmployeeDocumentExpiry::findOrFail($id);

        $fileFormats = explode(',', global_setting()->allowed_file_types);

        if ($request->hasFile('file')) {
            // if (!in_array($request->file->getClientMimeType(), $fileFormats)) {
            //     return Reply::error(__('messages.employeeDocsAllowedFormat'));
            // }
        }

        $document->document_name = $request->document_name;
        $document->document_number = $request->document_number;
        
        // Parse dates with proper format handling
        try {
            $document->issue_date = Carbon::createFromFormat(company()->date_format, $request->issue_date)->format('Y-m-d');
            $document->expiry_date = Carbon::createFromFormat(company()->date_format, $request->expiry_date)->format('Y-m-d');
        } catch (\Exception $e) {
            // Fallback to Carbon::parse if format doesn't match
            $document->issue_date = Carbon::parse($request->issue_date)->format('Y-m-d');
            $document->expiry_date = Carbon::parse($request->expiry_date)->format('Y-m-d');
        }
        $document->alert_before_days = $request->alert_before_days;
        $document->alert_enabled = $request->alert_enabled;
        $document->last_updated_by = user()->id;

        if ($request->hasFile('file')) {
            // Delete old file if exists
            if ($document->hashname) {
                Files::deleteFile($document->hashname, EmployeeDocumentExpiry::FILE_PATH . '/' . $document->user_id);
            }

            $filename = Files::uploadLocalOrS3($request->file, EmployeeDocumentExpiry::FILE_PATH . '/' . $document->user_id);
            $document->filename = $request->file->getClientOriginalName();
            $document->hashname = $filename;
            $document->size = $request->file->getSize();
        }

        $document->save();

        $this->documents = EmployeeDocumentExpiry::where('user_id', $document->user_id)
        ->orderByDesc('id')
        ->get();

        
        $view = view('employees.ajax.document-expiry.show', $this->data)->render();

        return Reply::successWithData(__('messages.updateSuccess'), ['view' => $view]);
    }

    public function destroy($id)
    {
        $document = EmployeeDocumentExpiry::findOrFail($id);
        $deleteDocumentPermission = user()->permission('delete_documents');

        abort_403(!($deleteDocumentPermission == 'all'
            || ($deleteDocumentPermission == 'added' && $document->added_by == user()->id)
            || ($deleteDocumentPermission == 'owned' && ($document->user_id == user()->id && $document->added_by != user()->id))
            || ($deleteDocumentPermission == 'both' && ($document->added_by == user()->id || $document->user_id == user()->id))));

        if ($document->hashname) {
            Files::deleteFile($document->hashname, EmployeeDocumentExpiry::FILE_PATH . '/' . $document->user_id);
        }

        EmployeeDocumentExpiry::destroy($id);

        $this->documents = EmployeeDocumentExpiry::where('user_id', $document->user_id)
            ->orderByDesc('id')
            ->get();

        $view = view('employees.ajax.document-expiry.show', $this->data)->render();

        return Reply::successWithData(__('messages.deleteSuccess'), ['view' => $view]);
    }

    public function download($id)
    {
        $this->document = EmployeeDocumentExpiry::whereRaw('md5(id) = ?', $id)->firstOrFail();
        $viewPermission = user()->permission('view_documents');

        abort_403(!($viewPermission == 'all'
            || ($viewPermission == 'added' && $this->document->added_by == user()->id)
            || ($viewPermission == 'owned' && ($this->document->user_id == user()->id && $this->document->added_by != user()->id))
            || ($viewPermission == 'both' && ($this->document->added_by == user()->id || $this->document->user_id == user()->id))));

        if (!$this->document->hashname) {
            return Reply::error(__('messages.fileNotFound'));
        }

        return download_local_s3($this->document, EmployeeDocumentExpiry::FILE_PATH . '/' . $this->document->user_id . '/' . $this->document->hashname);
    }
}
