<?php

namespace App\Http\Controllers;

use App\Helper\Files;
use App\Helper\Reply;
use App\Http\Requests\EmployeeDocs\CreateRequest;
use App\Http\Requests\EmployeeDocs\UpdateRequest;
use App\Models\EmployeeDocument;
use App\Models\User;

class EmployeeDocController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'app.menu.employeeDocs';
        $this->middleware(function ($request, $next) {
            abort_403(!in_array('employees', $this->user->modules));

            return $next($request);
        });
    }

    public function create()
    {
        $addPermission = user()->permission('add_documents');

        abort_403(!($addPermission == 'all'));

        $this->user = User::findOrFail(user()->id);

        return view('profile-settings.ajax.employee.create', $this->data);
    }

    public function store(CreateRequest $request)
    {
        abort_403(user()->permission('add_documents') != 'all');

        // Someone who sees only their own documents adds only to their own
        abort_403(user()->permission('view_documents') != 'all' && (int) $request->user_id !== (int) user()->id);

        $fileFormats = explode(',', global_setting()->allowed_file_types);

        foreach ($request->file as $fFormat) {
            if (!in_array($fFormat->getClientMimeType(), $fileFormats)) {
                return Reply::error(__('messages.employeeDocsAllowedFormat'));
            }
        }

        $file = new EmployeeDocument();

        $file->name = $request->name;
        $file->doc_type = \App\Services\Employee\DocumentTypes::clean($request->doc_type);
        $filename = Files::uploadLocalOrS3($request->file, EmployeeDocument::FILE_PATH . '/' . $request->user_id);

        $file->user_id = $request->user_id;
        $file->filename = $request->file->getClientOriginalName();
        $file->hashname = $filename;
        $file->size = $request->file->getSize();
        $file->save();

        $this->files = EmployeeDocument::where('user_id', $request->user_id)->orderByDesc('id')->get();
        $view = view('employees.files.show', $this->data)->render();

        return Reply::successWithData(__('messages.recordSaved'), ['status' => 'success', 'view' => $view]);
    }

    public function edit($id)
    {
        $this->file = EmployeeDocument::findOrFail($id);
        $editPermission = user()->permission('edit_documents');

        abort_403(!($editPermission == 'all'
            || ($editPermission == 'added' && $this->file->added_by == user()->id)
            || ($editPermission == 'owned' && ($this->file->user_id == user()->id && $this->file->added_by != user()->id))
            || ($editPermission == 'both' && ($this->file->added_by == user()->id || $this->file->user_id == user()->id))));

        return view('employees.files.edit', $this->data);
    }

    public function update(UpdateRequest $request, $id)
    {
        $file = EmployeeDocument::findOrFail($id);

        $file->name = $request->name;

        if ($request->has('doc_type')) {
            $file->doc_type = \App\Services\Employee\DocumentTypes::clean($request->doc_type);
        }

        if ($request->file) {
            $filename = Files::uploadLocalOrS3($request->file, EmployeeDocument::FILE_PATH . '/' . $file->user_id);
            $file->filename = $request->file->getClientOriginalName();
            $file->hashname = $filename;
            $file->size = $request->file->getSize();
        }

        $file->save();

        return Reply::success(__('messages.updateSuccess'));
    }

    public function destroy($id)
    {
        $file = EmployeeDocument::findOrFail($id);
        $deleteDocumentPermission = user()->permission('delete_documents');

        abort_403(!($deleteDocumentPermission == 'all'
            || ($deleteDocumentPermission == 'added' && $file->added_by == user()->id)
            || ($deleteDocumentPermission == 'owned' && ($file->user_id == user()->id && $file->added_by != user()->id))
            || ($deleteDocumentPermission == 'both' && ($file->added_by == user()->id || $file->user_id == user()->id))));


        Files::deleteFile($file->hashname, EmployeeDocument::FILE_PATH . '/' . $file->user_id);

        EmployeeDocument::destroy($id);

        $this->files = EmployeeDocument::where('user_id', $file->user_id)->orderByDesc('id')->get();

        $view = view('employees.files.show', $this->data)->render();

        return Reply::successWithData(__('messages.deleteSuccess'), ['view' => $view]);

    }

    public function download($id)
    {
        $this->file = EmployeeDocument::whereRaw('md5(id) = ?', $id)->firstOrFail();
        $viewPermission = user()->permission('view_documents');

        abort_403(!($viewPermission == 'all'
            || ($viewPermission == 'added' && $this->file->added_by == user()->id)
            || ($viewPermission == 'owned' && ($this->file->user_id == user()->id && $this->file->added_by != user()->id))
            || ($viewPermission == 'both' && ($this->file->added_by == user()->id || $this->file->user_id == user()->id))));

        return download_local_s3($this->file, EmployeeDocument::FILE_PATH . '/' . $this->file->user_id . '/' . $this->file->hashname);

    }

}
