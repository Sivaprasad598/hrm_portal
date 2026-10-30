<?php

namespace App\Http\Controllers;

use App\Helper\Files;
use App\Helper\Reply;
use App\Http\Requests\User\UpdateProfile;
use App\Models\ClientContact;
use App\Models\EmployeeDetails;
use App\Models\User;
use App\Scopes\ActiveScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends AccountBaseController
{

    // phpcs:ignore
    public function update(UpdateProfile $request, $id)
    {

        $redirect = false;

        // if (session()->has('clientContact') && session('clientContact')) {

        //     $clientContact = ClientContact::findOrFail(session('clientContact')->id);
        //     $clientContact->contact_name = $request->name;
        //     $clientContact->phone = $request->mobile;
        //     $clientContact->email = $request->email;
        //     $clientContact->save();

        //     session(['clientContact' => $clientContact]);

        //     $user = User::withoutGlobalScope(ActiveScope::class)->findOrFail(session('clientContact')->client_id);
        // }else{
            $user = user();
        // }
        // For profile image to be uploaded locally
        $user->name = $request->name;
        $user->email = $request->email;
        $user->salutation = $request->salutation;
        $user->gender = $request->gender;
        $user->country_id = $request->country_id;
        $user->country_phonecode = $request->country_phonecode;
        $user->mobile = $request->mobile;
        // Fields no longer on the form (email notifications, language, Google
        // Calendar) keep what they were rather than being emptied.
        foreach (['email_notifications', 'locale', 'rtl', 'google_calendar_status', 'twitter_id'] as $field) {
            if ($request->has($field)) {
                $user->{$field} = $request->{$field};
            }
        }

        if (!is_null($request->password)) {
            $user->password = Hash::make($request->password);
        }

        if ($request->image_delete == 'yes') {
            Files::deleteFile($user->image, 'avatar');
            $user->image = null;
        }

        if ($request->hasFile('image')) {
            Files::deleteFile($user->image, 'avatar');
            $user->image = Files::uploadLocalOrS3($request->image, 'avatar', 300);
        }

        if ($request->has('telegram_user_id')) {
            $user->telegram_user_id = $request->telegram_user_id;
        }

        if ($user->isDirty('locale')) {
            $redirect = true;
        }

        $user->save();

        if ($user->clientDetails) {
            $fields = $request->only($user->clientDetails->getFillable());

            $user->clientDetails->fill($fields);
            $user->clientDetails->save();

        } else {
            // adding address to employee_details
            $this->addEmployeeDetail($request, $user);
        }

        session()->forget('user');
        session()->forget('isRtl');


        $this->logUserActivity($user->id, 'messages.updatedProfile');

        $redirectUrl = urldecode($request->redirect_url);

        if ($redirectUrl == '') {
            $redirectUrl = route('profile-settings.index');
        }

        return Reply::successWithData(__('messages.updateSuccess'), ['redirectUrl' => $redirectUrl, 'redirect' => $redirect]);
    }

    public function addEmployeeDetail($request, $user)
    {
        $employee = EmployeeDetails::where('user_id', $user->id)->first();

        if (empty($employee)) {
            $employee = new EmployeeDetails();
            $employee->user_id = $user->id;
        }

        $employee->date_of_birth = $request->date_of_birth ? companyToYmd($request->date_of_birth) : null;
        $employee->address = $request->address;

        // Slack and About are no longer on the form: kept as they were.
        if ($request->has('slack_username')) {
            $employee->slack_username = $request->slack_username;
        }

        if ($request->has('about_me')) {
            $employee->about_me = $request->about_me;
        }

        // The joining date drives payroll and leave: only someone who may edit
        // employees changes it here (the form shows it read-only to others).
        if ($request->filled('joining_date') && user()->permission('edit_employees') === 'all') {
            $employee->joining_date = companyToYmd($request->joining_date);
        }

        if (in_array('employee', user_roles())) {
            $employee->marital_status = $request->marital_status;
            $employee->marriage_anniversary_date = $request->marriage_anniversary_date ? companyToYmd($request->marriage_anniversary_date) : null;
        }

        $employee->save();
    }

    public function darkTheme(Request $request)
    {
        $user = user();
        $user->dark_theme = $request->darkTheme;
        $user->save();
        session()->forget('user');
        return Reply::success(__('messages.updateSuccess'));
    }

    public function updateOneSignalId(Request $request)
    {
        $user = user();
        $user->onesignal_player_id = $request->userId;
        $user->save();
        session()->forget('user');
    }

}
