<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Http\Requests\SearchRequest;

class SearchController extends AccountBaseController
{

    public function index()
    {
        return view('search.index', $this->data);
    }

    /**
     * @param SearchRequest $request
     * @return array|string[]|void
     */
    public function store(SearchRequest $request)
    {
        $module = $request->search_module;

        switch ($module) {
        case 'project':
            return Reply::redirect(\App\Support\ModulePresence::url('projects.index') . '?search_keyword=' . $request->search_keyword);
        case 'ticket':
            return Reply::redirect(\App\Support\ModulePresence::url('tickets.index') . '?search_keyword=' . $request->search_keyword);
        case 'invoice':
            return Reply::redirect(\App\Support\ModulePresence::url('invoices.index') . '?search_keyword=' . $request->search_keyword);
        case 'notice':
            return Reply::redirect(\App\Support\ModulePresence::url('notices.index') . '?search_keyword=' . $request->search_keyword);
        case 'task':
            return Reply::redirect(\App\Support\ModulePresence::url('tasks.index') . '?search_keyword=' . $request->search_keyword);
        case 'creditNote':
            return Reply::redirect(\App\Support\ModulePresence::url('creditnotes.index') . '?search_keyword=' . $request->search_keyword);
        case 'employee':
            return Reply::redirect(\App\Support\ModulePresence::url('employees.index') . '?search_keyword=' . $request->search_keyword);
        case 'client':
            return Reply::redirect(\App\Support\ModulePresence::url('clients.index') . '?search_keyword=' . $request->search_keyword);
        case 'estimate':
            return Reply::redirect(\App\Support\ModulePresence::url('estimates.index') . '?search_keyword=' . $request->search_keyword);
        case 'lead':
            return Reply::redirect(\App\Support\ModulePresence::url('deals.index') . '?search_keyword=' . $request->search_keyword);
        default:
            // Code...
            break;
        }
    }

}
