<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Services\Employee\RelievingLetter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Relieving and experience letters, from the Exited Employees list: the
 * employee is checked (left, assets returned, final settlement paid, record
 * complete), HR confirms the letter date, and both letters are issued together;
 * the relieving letter opens (the experience letter one click away) to print or
 * download. Each PDF goes into the employee's documents.
 */
class RelievingLetterController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'Relieving Letter';

        $this->middleware(function ($request, $next) {
            abort_403(!self::canUse());

            return $next($request);
        });
    }

    /** As the offer letter: the administrators and whoever edits employees. */
    public static function canUse(): bool
    {
        return (bool) array_intersect(['admin', 'manager_admin'], user_roles())
            || user()->permission('edit_employees') === 'all';
    }

    public function check($userId)
    {
        $latest = RelievingLetter::latestFor((int) $userId);

        return Reply::dataOnly([
            'status' => 'success',
            'problems' => RelievingLetter::problems((int) $userId),
            'edit_url' => route('employees.edit', $userId),
            'today' => now()->toDateString(),
            'existing' => $latest ? [
                'date' => Carbon::parse($latest->letter_date)->format(company()->date_format),
                'url' => route('relieving-letters.show', $latest->id),
            ] : null,
        ]);
    }

    public function issue(Request $request, $userId)
    {
        $problems = RelievingLetter::problems((int) $userId);

        if ($problems) {
            return Reply::error('Not yet: ' . implode(' ', $problems));
        }

        $date = $request->letter_date;

        if ($date && !strtotime($date)) {
            return Reply::error('Pick a valid letter date.');
        }

        $letter = RelievingLetter::issue((int) $userId, $date);

        return Reply::successWithData('Relieving and experience letters generated.', ['redirect_url' => route('relieving-letters.show', $letter->id)]);
    }

    public function show($id)
    {
        $this->letter = $this->letter($id);
        $this->pair = RelievingLetter::pairOf($this->letter);
        $this->label = RelievingLetter::label($this->letter);
        $this->employee = ($this->letter->user_id ? DB::table('users')->where('id', $this->letter->user_id)->first(['id', 'name']) : null)
            ?: (object) ['id' => null, 'name' => $this->letter->person_name];
        $this->pageTitle = $this->label . ' - ' . $this->employee->name;

        return view('relieving-letters.show', $this->data);
    }

    public function view($id)
    {
        return response(RelievingLetter::html($this->letter($id), false));
    }

    public function pdf(Request $request, $id)
    {
        $letter = $this->letter($id);
        $binary = RelievingLetter::pdf($letter)->output();

        try {
            RelievingLetter::keep($letter, $binary);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Relieving letter not kept with the documents: ' . $e->getMessage());
        }

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('inline') ? 'inline' : 'attachment') . '; filename="' . RelievingLetter::fileName($letter) . '"',
        ]);
    }

    protected function letter($id): object
    {
        $letter = DB::table('relieving_letters')->where('id', $id)->where('company_id', company()->id)->first();
        abort_if(!$letter, 404);

        return $letter;
    }

}
