<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Services\Employee\OfferLetter;
use App\Services\Employee\RelievingLetter;
use Illuminate\Http\Request;

/**
 * Settings -> Letter Settings: every word of the offer and relieving letters, their
 * shared footer and stamp, numbering and the notice during probation, with a preview.
 */
class OfferLetterSettingController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'Letter Settings';
        $this->activeSettingMenu = 'offer_letter_settings';

        $this->middleware(function ($request, $next) {
            abort_403(!self::canEdit());

            return $next($request);
        });
    }

    /** As Notice Period Settings: company settings, or whoever registers employees. */
    public static function canEdit(): bool
    {
        return (bool) array_intersect(['admin', 'manager_admin'], user_roles())
            || user()->permission('manage_company_setting') === 'all'
            || user()->permission('edit_employees') === 'all';
    }

    public function index()
    {
        $this->settings = OfferLetter::settings();
        $this->defaults = OfferLetter::DEFAULTS;
        $this->placeholders = OfferLetter::PLACEHOLDERS;
        $this->suggested = OfferLetter::suggestReference();
        $this->relievingPlaceholders = RelievingLetter::PLACEHOLDERS;
        $this->hikePlaceholders = \App\Services\Payroll\SalaryRevisionLetter::HIKE_PLACEHOLDERS;
        $this->stampUrl = OfferLetter::stamp(['show_stamp' => 1] + $this->settings, false);

        return view('offer-letter-settings.index', $this->data);
    }

    public function update(Request $request)
    {
        $values = $this->values($request);

        if (!str_contains($values['reference_format'], '{seq}')) {
            return Reply::error('The reference format needs {seq} for the running number, e.g. {seq}/{year}.');
        }

        // The stamp image: uploaded here (public/user-uploads/letters), or back to the company's own.
        $current = OfferLetter::settings();
        $values['stamp_image'] = $current['stamp_image'];
        $reload = false;

        if ($request->boolean('remove_stamp')) {
            $values['stamp_image'] = '';
            $reload = true;
        }

        if ($request->hasFile('stamp_file')) {
            $request->validate(['stamp_file' => 'image|mimes:png,jpg,jpeg|max:4096']);
            $dir = public_path('user-uploads/letters');

            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $name = 'stamp-' . now()->format('YmdHis') . '.' . strtolower($request->file('stamp_file')->getClientOriginalExtension());
            $request->file('stamp_file')->move($dir, $name);
            $values['stamp_image'] = 'user-uploads/letters/' . $name;
            $reload = true;
        }

        OfferLetter::saveSettings($values);

        return Reply::successWithData(__('messages.updateSuccess'), ['reload' => $reload]);
    }

    /** The letter with the settings as they are on the form (not saved), for a made-up employee. */
    public function preview(Request $request)
    {
        $settings = array_merge(OfferLetter::DEFAULTS, $this->values($request));
        $settings['show_zero_rows'] = (int) $request->boolean('show_zero_rows');
        $settings['show_stamp'] = (int) $request->boolean('show_stamp');
        $settings['stamp_image'] = OfferLetter::settings()['stamp_image'];

        return response(match ($request->preview) {
            'relieving' => RelievingLetter::html(null, false, $settings),
            'hike' => \App\Services\Payroll\SalaryRevisionLetter::hikePreview($settings),
            default => OfferLetter::html(null, false, $settings),
        });
    }

    protected function values(Request $request): array
    {
        $values = [];

        foreach (array_keys(OfferLetter::DEFAULTS) as $key) {
            if ($key === 'stamp_image') {
                continue;
            }

            if (in_array($key, ['show_zero_rows', 'show_stamp'], true)) {
                $values[$key] = (int) $request->boolean($key);
            } elseif ($request->has($key)) {
                $values[$key] = trim((string) $request->input($key));
            }
        }

        return $values;
    }

}
