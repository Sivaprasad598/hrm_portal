<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Models\EmployeeDetails;
use App\Models\Resignation;
use App\Models\ResignationReply;
use App\Notifications\NewResignationMessage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class ResignationController extends AccountBaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'Resignation';
    }

    public function index()
    {
        abort_403(in_array('client', user_roles()));

        $employeeDetail = EmployeeDetails::where('user_id', user()->id)->first();

        $this->employee = user();
        $this->employeeDetail = $employeeDetail;
        $this->employmentStatus = $employeeDetail
            ? EmployeeDetails::where('id', $employeeDetail->id)
                ->selectRaw(EmployeeDetails::employmentStatusSql() . ' as status')
                ->value('status')
            : 'confirmed';
        $this->resignations = Resignation::where('user_id', user()->id)->with('replies.sender')->orderByDesc('created_at')->get();
        $this->pendingResignation = $this->resignations->firstWhere('status', 'pending');
        $this->resignationJsData = $this->buildResignationJsData($this->resignations);

        return view('resignations.index', $this->data);
    }

    private function buildResignationJsData($resignations)
    {
        $data = [];

        foreach ($resignations as $resignation) {
            $messages = [];

            foreach ($resignation->replies as $reply) {
                $messages[] = [
                    'sender_type' => $reply->sender_type,
                    'sender_name' => $reply->sender_type === 'admin' ? ($reply->sender->name ?? 'Admin') : 'You',
                    'message'     => $reply->message,
                    'time'        => $reply->created_at->format(company()->date_format . ' h:i A'),
                ];
            }

            $data[$resignation->id] = [
                'id'                   => $resignation->id,
                'resignation_date_ymd' => optional($resignation->resignation_date)->format('Y-m-d'),
                'notice_served'        => $resignation->notice_served,
                'reason'               => $resignation->reason,
                'remarks'              => $resignation->remarks,
                'status'               => $resignation->status,
                'submitted_at'         => $resignation->created_at->format(company()->date_format . ' h:i A'),
                'messages'             => $messages,
            ];
        }

        return $data;
    }

    public function store(Request $request)
    {
        abort_403(in_array('client', user_roles()));

        if (Resignation::where('user_id', user()->id)->where('status', 'pending')->exists()) {
            return Reply::error('You already have a resignation request pending HR review.');
        }

        $request->validate([
            'resignation_date' => 'required|date',
            'reason'           => 'required|string|max:255',
            'notice_served'    => 'nullable|in:yes,no',
            'remarks'          => 'nullable|max:1000',
        ]);

        $resignationDate = Carbon::parse($request->resignation_date);

        Resignation::create([
            'user_id'           => user()->id,
            'company_id'        => company()->id,
            'exit_type'         => 'resignation',
            'reason'            => $request->reason,
            'resignation_date'  => $resignationDate->toDateString(),
            'notice_start_date' => $resignationDate->toDateString(),
            'notice_served'     => $request->notice_served,
            'remarks'           => $request->remarks,
            'status'            => 'pending',
        ]);

        return Reply::successWithData(
            'Your resignation has been submitted successfully. HR will review it shortly.',
            ['redirectUrl' => route('resignations.index')]
        );
    }

    public function update(Request $request, $id)
    {
        abort_403(in_array('client', user_roles()));

        $resignation = Resignation::where('user_id', user()->id)->findOrFail($id);

        if ($resignation->status !== 'pending') {
            return Reply::error('This resignation has already been reviewed by HR and can no longer be edited.');
        }

        $request->validate([
            'resignation_date' => 'required|date',
            'reason'           => 'required|string|max:255',
            'notice_served'    => 'nullable|in:yes,no',
            'remarks'          => 'nullable|max:1000',
        ]);

        $resignationDate = Carbon::parse($request->resignation_date);

        $resignation->update([
            'reason'            => $request->reason,
            'resignation_date'  => $resignationDate->toDateString(),
            'notice_start_date' => $resignationDate->toDateString(),
            'notice_served'     => $request->notice_served,
            'remarks'           => $request->remarks,
        ]);

        return Reply::successWithData(
            'Your resignation request has been updated.',
            ['redirectUrl' => route('resignations.index')]
        );
    }

    public function destroy($id)
    {
        abort_403(in_array('client', user_roles()));

        $resignation = Resignation::where('user_id', user()->id)->findOrFail($id);

        if ($resignation->status !== 'pending') {
            return Reply::error('This resignation has already been reviewed by HR and can no longer be withdrawn.');
        }

        $resignation->delete();

        return Reply::successWithData(
            'Your resignation request has been withdrawn.',
            ['redirectUrl' => route('resignations.index')]
        );
    }

    public function reply(Request $request, $id)
    {
        abort_403(in_array('client', user_roles()));

        $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        $resignation = Resignation::where('user_id', user()->id)->findOrFail($id);

        ResignationReply::create([
            'resignation_id' => $resignation->id,
            'user_id'        => user()->id,
            'sender_type'    => 'employee',
            'message'        => $request->message,
        ]);

        $recipients = Resignation::adminRecipients($resignation->company_id);
        Notification::send($recipients, new NewResignationMessage($resignation, $request->message));

        return Reply::success('Your message has been sent to HR.');
    }
}
