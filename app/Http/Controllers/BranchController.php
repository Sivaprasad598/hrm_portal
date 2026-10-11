<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Models\Branch;
use App\Http\Requests\Branch\StoreBranch;

class BranchController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = __('app.menu.branch');

        $this->middleware(function ($request, $next) {
            abort_403(!in_array('employees', $this->user->modules));

            return $next($request);
        });
    }

    public function create()
    {
        return view('employees.create_branch', $this->data);
    }

    /**
     * @param StoreBranch $request
     * @return array
     * @throws \Froiden\RestAPI\Exceptions\RelatedResourceNotFoundException
     */
    public function store(StoreBranch $request)
    {
        $branch = new Branch();
        $branch->branch_name = $request->branch_name;
        $branch->save();

        $this->branches = Branch::allBranches();

        return Reply::successWithData(__('messages.recordSaved'), ['branches' => $this->branches]);
    }

}
