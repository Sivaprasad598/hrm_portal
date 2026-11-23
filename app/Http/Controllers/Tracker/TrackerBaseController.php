<?php

namespace App\Http\Controllers\Tracker;

use App\Helper\Reply;
use App\Http\Controllers\AccountBaseController;

/**
 * What the project tracker pages share: side panels that also open as a page of their own
 * (docs/PAGE_RULES.md), and refusals as the forms expect them.
 */
abstract class TrackerBaseController extends AccountBaseController
{

    protected function panel(string $view, string $title)
    {
        $this->pageTitle = $title;
        $this->view = $view;

        if (request()->ajax()) {
            return Reply::dataOnly(['status' => 'success', 'html' => view($view, $this->data)->render(), 'title' => $title]);
        }

        return view('recruitment.page', $this->data);
    }

    protected function fail(string $message): never
    {
        abort(response()->json(['status' => 'fail', 'message' => $message, 'errors' => []], 422));
    }

}
