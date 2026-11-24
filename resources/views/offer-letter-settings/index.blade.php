@extends('layouts.app')

@push('styles')
<style>
    .ol-section { border: 1px solid #e8eef3; border-radius: 6px; margin-bottom: 16px; }
    .ol-section > h4 { font-size: 14px; font-weight: 600; margin: 0; padding: 10px 14px; border-bottom: 1px solid #e8eef3; background: #f8fafc; }
    .ol-section .ol-body { padding: 14px 14px 2px; }
    .ol-section label { font-size: 13px; font-weight: 500; color: #28313c; }
    .ol-section textarea { font-size: 13px; }
    /* Under a field: pulled up under a text box (its form-group has a bottom margin), not under a drop-down (mb-0). */
    .ol-help { font-size: 12px; color: #99a5b5; margin-top: 6px; margin-bottom: 12px; line-height: 1.4; }
    .form-group:not(.mb-0) + .ol-help { margin-top: -10px; }
    .ol-ph { font-size: 12px; }
    .ol-ph code { cursor: pointer; }
    .ol-ph td { padding: 2px 8px 2px 0; vertical-align: top; }
    .ol-tabs .nav-link { font-size: 14px; color: #616e80; }
    .ol-tabs .nav-link.active { color: #28313c; font-weight: 600; }
    .ol-stamp img { max-height: 110px; border: 1px dashed #c9d2dc; padding: 4px; background: #fff; }
</style>
@endpush

@section('content')

    <div class="w-100 d-flex">

        @include('sections.setting-sidebar')

        <x-setting-card>
            <x-slot name="header">
                <div class="s-b-n-header" id="tabs">
                    <h2 class="mb-0 p-20 f-21 font-weight-normal border-bottom-grey">Letter Settings</h2>
                </div>
            </x-slot>

            <div class="col-lg-12 col-md-12 w-100 p-4">
                {{-- Not a <form>: the settings card around it is one already (see letterFields() below). --}}
                <div id="offer-settings-form">
                    @csrf
                    <input type="hidden" name="preview" id="preview-which" value="offer">

                    <div class="alert alert-info f-13">
                        Every word of the offer, relieving and experience letters is here. Placeholders in curly brackets are filled in for each
                        employee (click one to copy it); put text between <code>**</code> to make it <strong>bold</strong>.
                        Paragraphs are separated by a blank line. <strong>Preview</strong> shows the letter as it is on this form,
                        for a sample employee, before you save. Both letters share the footer and the stamp.
                    </div>

                    <ul class="nav nav-tabs ol-tabs mb-3" role="tablist">
                        <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#tab-offer" data-preview="offer" role="tab">Offer Letter</a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-relieving" data-preview="relieving" role="tab">Relieving Letter</a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-experience" data-preview="experience" role="tab">Experience Letter</a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-hike" data-preview="hike" role="tab">Hike Letter</a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-footer" role="tab">Footer &amp; Stamp</a></li>
                    </ul>

                    <div class="tab-content">

                        {{-- ============================== Offer letter ============================== --}}
                        <div class="tab-pane fade show active" id="tab-offer" role="tabpanel">
                            <div class="ol-section">
                                <h4>Placeholders</h4>
                                <div class="ol-body">
                                    <table class="ol-ph mb-3">
                                        @foreach (array_chunk($placeholders, 2, true) as $pair)
                                            <tr>
                                                @foreach ($pair as $code => $meaning)
                                                    <td><code>{{ $code }}</code></td><td class="text-dark-grey pr-4">{{ $meaning }}</td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </table>
                                </div>
                            </div>

                            <div class="ol-section">
                                <h4>Reference number &amp; notice</h4>
                                <div class="ol-body row">
                                    <div class="col-md-4">
                                        <x-forms.text fieldId="reference_format" fieldLabel="Reference format" fieldName="reference_format"
                                            :fieldValue="$settings['reference_format']" fieldRequired="true"/>
                                        <div class="ol-help"><code>{seq}</code> running number, <code>{year}</code> current year. Next: <strong>{{ $suggested }}</strong></div>
                                    </div>
                                    <div class="col-md-2">
                                        <x-forms.number fieldId="reference_digits" fieldLabel="Digits in {seq}" fieldName="reference_digits"
                                            :fieldValue="$settings['reference_digits']" minValue="1" maxValue="8"/>
                                    </div>
                                    <div class="col-md-3">
                                        <x-forms.number fieldId="notice_probation_days" fieldLabel="Notice during probation (days)" fieldName="notice_probation_days"
                                            :fieldValue="$settings['notice_probation_days']" minValue="0"/>
                                        <div class="ol-help">For <code>{notice_probation}</code>.</div>
                                    </div>
                                    <div class="col-md-3">
                                        <x-forms.select fieldId="notice_unit" fieldLabel="Notice after confirmation shown in" fieldName="notice_unit">
                                            <option value="days" @selected($settings['notice_unit'] === 'days')>Days (2 months = 60 days)</option>
                                            <option value="months" @selected($settings['notice_unit'] === 'months')>Months (2 months)</option>
                                        </x-forms.select>
                                        <div class="ol-help">For <code>{notice_confirmed}</code>: the employee's Notice Period.</div>
                                    </div>
                                </div>
                            </div>

                            <div class="ol-section">
                                <h4>Letter</h4>
                                <div class="ol-body">
                                    <x-forms.text fieldId="subject" fieldLabel="Subject (after “Sub.:”)" fieldName="subject" :fieldValue="$settings['subject']"/>
                                    <div class="form-group">
                                        <label for="paragraphs">Paragraphs</label>
                                        <textarea class="form-control" id="paragraphs" name="paragraphs" rows="16">{{ $settings['paragraphs'] }}</textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="no_probation_paragraph">When there is no probation (used in place of the paragraph with <code>{probation}</code>)</label>
                                        <textarea class="form-control" id="no_probation_paragraph" name="no_probation_paragraph" rows="3">{{ $settings['no_probation_paragraph'] }}</textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="documents">Documents to bring (one per line, numbered in the letter)</label>
                                        <textarea class="form-control" id="documents" name="documents" rows="6">{{ $settings['documents'] }}</textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="note">Note</label>
                                        <textarea class="form-control" id="note" name="note" rows="2">{{ $settings['note'] }}</textarea>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <x-forms.text fieldId="closing" fieldLabel="Closing" fieldName="closing" :fieldValue="$settings['closing']"/>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="signoff">Signed off by (one per line)</label>
                                                <textarea class="form-control" id="signoff" name="signoff" rows="3">{{ $settings['signoff'] }}</textarea>
                                            </div>
                                            <div class="ol-help">An empty line (no designation on record) is left out.</div>
                                        </div>
                                        <div class="col-md-4">
                                            <x-forms.text fieldId="enclosure" fieldLabel="Enclosure line" fieldName="enclosure" :fieldValue="$settings['enclosure']"/>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="ol-section">
                                <h4>Annexure I - Salary Structure</h4>
                                <div class="ol-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <x-forms.text fieldId="annexure_title" fieldLabel="Title" fieldName="annexure_title" :fieldValue="$settings['annexure_title']"/>
                                        </div>
                                        <div class="col-md-4">
                                            <x-forms.text fieldId="annexure_company" fieldLabel="Company line" fieldName="annexure_company" :fieldValue="$settings['annexure_company']"/>
                                        </div>
                                        <div class="col-md-4">
                                            <x-forms.text fieldId="annexure_heading" fieldLabel="Heading" fieldName="annexure_heading" :fieldValue="$settings['annexure_heading']"/>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="acknowledgement">Acknowledgement</label>
                                        <textarea class="form-control" id="acknowledgement" name="acknowledgement" rows="2">{{ $settings['acknowledgement'] }}</textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="acceptance">Acceptance</label>
                                        <textarea class="form-control" id="acceptance" name="acceptance" rows="3">{{ $settings['acceptance'] }}</textarea>
                                    </div>
                                    <div class="form-group">
                                        <input type="hidden" name="show_zero_rows" value="0">
                                        <label class="mb-0"><input type="checkbox" name="show_zero_rows" value="1" class="mr-2" @checked($settings['show_zero_rows'])>
                                            Show components that are zero (e.g. ESIC) with a dash</label>
                                    </div>
                                    <div class="ol-help mt-1">The amounts are payroll's own calculation from the employee's annual CTC and Basic % (Payroll Settings).</div>
                                </div>
                            </div>
                        </div>

                        {{-- ============================== Relieving letter ============================== --}}
                        <div class="tab-pane fade" id="tab-relieving" role="tabpanel">
                            <div class="ol-section">
                                <h4>Placeholders</h4>
                                <div class="ol-body">
                                    <table class="ol-ph mb-3">
                                        @foreach (array_chunk($relievingPlaceholders, 2, true) as $pair)
                                            <tr>
                                                @foreach ($pair as $code => $meaning)
                                                    <td><code>{{ $code }}</code></td><td class="text-dark-grey pr-4">{{ $meaning }}</td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </table>
                                    <div class="ol-help">**text** prints bold. A blank line starts a new paragraph.</div>
                                </div>
                            </div>

                            <div class="ol-section">
                                <h4>Letter</h4>
                                <div class="ol-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <x-forms.text fieldId="relieving_title" fieldLabel="Title" fieldName="relieving_title" :fieldValue="$settings['relieving_title']"/>
                                        </div>
                                        <div class="col-md-3">
                                            <x-forms.select fieldId="relieving_date_format" fieldLabel="Dates in the letter" fieldName="relieving_date_format">
                                                @foreach ($dateFormats as $fmt => $example)
                                                    <option value="{{ $fmt }}" @selected($settings['relieving_date_format'] === $fmt)>{{ $example }}</option>
                                                @endforeach
                                            </x-forms.select>
                                            <div class="ol-help">For <code>{joining_date}</code> and <code>{relieving_date}</code>.</div>
                                        </div>
                                        <div class="col-md-3">
                                            <x-forms.select fieldId="relieving_letter_date_format" fieldLabel="Letter date" fieldName="relieving_letter_date_format">
                                                @foreach ($dateFormats as $fmt => $example)
                                                    <option value="{{ $fmt }}" @selected($settings['relieving_letter_date_format'] === $fmt)>{{ $example }}</option>
                                                @endforeach
                                            </x-forms.select>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="relieving_to">To (one line each, after “To,”)</label>
                                                <textarea class="form-control" id="relieving_to" name="relieving_to" rows="4">{{ $settings['relieving_to'] }}</textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <x-forms.text fieldId="relieving_subject" fieldLabel="Subject (after “Subject:”, empty for none)" fieldName="relieving_subject" :fieldValue="$settings['relieving_subject']"/>
                                            <x-forms.text fieldId="relieving_greeting" fieldLabel="Greeting" fieldName="relieving_greeting" :fieldValue="$settings['relieving_greeting']"/>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="relieving_paragraphs">Paragraphs</label>
                                        <textarea class="form-control" id="relieving_paragraphs" name="relieving_paragraphs" rows="12">{{ $settings['relieving_paragraphs'] }}</textarea>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <x-forms.text fieldId="relieving_closing" fieldLabel="Closing (optional)" fieldName="relieving_closing" :fieldValue="$settings['relieving_closing']"/>
                                        </div>
                                        <div class="col-md-8">
                                            <div class="form-group">
                                                <label for="relieving_signoff">Signed off by (one per line)</label>
                                                <textarea class="form-control" id="relieving_signoff" name="relieving_signoff" rows="3">{{ $settings['relieving_signoff'] }}</textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="ol-help mt-1">Issued with the Experience Letter from Exiting Employees → Exited Employees, once assets are returned and the final settlement is paid; or from the Letter Generator.</div>
                                </div>
                            </div>
                        </div>

                        {{-- ============================== Experience letter ============================== --}}
                        <div class="tab-pane fade" id="tab-experience" role="tabpanel">
                            <div class="ol-section">
                                <h4>Placeholders</h4>
                                <div class="ol-body">
                                    <table class="ol-ph mb-3">
                                        @foreach (array_chunk($relievingPlaceholders, 2, true) as $pair)
                                            <tr>
                                                @foreach ($pair as $code => $meaning)
                                                    <td><code>{{ $code }}</code></td><td class="text-dark-grey pr-4">{{ $meaning }}</td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </table>
                                    <div class="ol-help">**text** prints bold. A blank line starts a new paragraph.</div>
                                </div>
                            </div>

                            <div class="ol-section">
                                <h4>Letter</h4>
                                <div class="ol-body">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <x-forms.text fieldId="experience_title" fieldLabel="Title" fieldName="experience_title" :fieldValue="$settings['experience_title']"/>
                                        </div>
                                        <div class="col-md-3">
                                            <x-forms.text fieldId="experience_heading" fieldLabel="Heading under it" fieldName="experience_heading" :fieldValue="$settings['experience_heading']"/>
                                        </div>
                                        <div class="col-md-3">
                                            <x-forms.select fieldId="experience_date_format" fieldLabel="Dates in the letter" fieldName="experience_date_format">
                                                @foreach ($dateFormats as $fmt => $example)
                                                    <option value="{{ $fmt }}" @selected($settings['experience_date_format'] === $fmt)>{{ $example }}</option>
                                                @endforeach
                                            </x-forms.select>
                                            <div class="ol-help">For <code>{joining_date}</code> and <code>{relieving_date}</code>.</div>
                                        </div>
                                        <div class="col-md-3">
                                            <x-forms.select fieldId="experience_letter_date_format" fieldLabel="Letter date" fieldName="experience_letter_date_format">
                                                @foreach ($dateFormats as $fmt => $example)
                                                    <option value="{{ $fmt }}" @selected($settings['experience_letter_date_format'] === $fmt)>{{ $example }}</option>
                                                @endforeach
                                            </x-forms.select>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="experience_intro">Opening (above the particulars)</label>
                                        <textarea class="form-control" id="experience_intro" name="experience_intro" rows="5">{{ $settings['experience_intro'] }}</textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="experience_particulars">Particulars table: one row a line, <code>Label | value</code> (the first line is the heading row; empty for no table)</label>
                                        <textarea class="form-control" id="experience_particulars" name="experience_particulars" rows="8">{{ $settings['experience_particulars'] }}</textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="experience_paragraphs">Paragraphs (below the particulars)</label>
                                        <textarea class="form-control" id="experience_paragraphs" name="experience_paragraphs" rows="7">{{ $settings['experience_paragraphs'] }}</textarea>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <x-forms.text fieldId="experience_closing" fieldLabel="Closing (optional)" fieldName="experience_closing" :fieldValue="$settings['experience_closing']"/>
                                        </div>
                                        <div class="col-md-8">
                                            <div class="form-group">
                                                <label for="experience_signoff">Signed off by (one per line)</label>
                                                <textarea class="form-control" id="experience_signoff" name="experience_signoff" rows="3">{{ $settings['experience_signoff'] }}</textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="ol-help mt-1">Issued with the Relieving Letter from Exiting Employees → Exited Employees, or from the Letter Generator.</div>
                                </div>
                            </div>
                        </div>

                        {{-- ============================== Hike letter ============================== --}}
                        <div class="tab-pane fade" id="tab-hike" role="tabpanel">
                            <div class="ol-section">
                                <h4>Placeholders</h4>
                                <div class="ol-body">
                                    <table class="ol-ph mb-3">
                                        @foreach (array_chunk($hikePlaceholders, 2, true) as $pair)
                                            <tr>
                                                @foreach ($pair as $code => $meaning)
                                                    <td><code>{{ $code }}</code></td><td class="text-dark-grey pr-4">{{ $meaning }}</td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </table>
                                </div>
                            </div>

                            <div class="ol-section">
                                <h4>Letter</h4>
                                <div class="ol-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <x-forms.text fieldId="hike_title" fieldLabel="Title" fieldName="hike_title" :fieldValue="$settings['hike_title']"/>
                                        </div>
                                        <div class="col-md-4">
                                            <x-forms.text fieldId="hike_subject" fieldLabel="Subject (after “Subject:”)" fieldName="hike_subject" :fieldValue="$settings['hike_subject']"/>
                                        </div>
                                        <div class="col-md-4">
                                            <x-forms.select fieldId="hike_date_format" fieldLabel="Dates in the letter" fieldName="hike_date_format">
                                                @foreach (['d/m/Y' => '01/04/2026', 'd-m-Y' => '01-04-2026', 'd M Y' => '01 Apr 2026', 'jS F Y' => '1st April 2026'] as $fmt => $example)
                                                    <option value="{{ $fmt }}" @selected($settings['hike_date_format'] === $fmt)>{{ $example }}</option>
                                                @endforeach
                                            </x-forms.select>
                                        </div>
                                    </div>
                                    <x-forms.text fieldId="hike_greeting" fieldLabel="Greeting" fieldName="hike_greeting" :fieldValue="$settings['hike_greeting']"/>
                                    <div class="form-group">
                                        <label for="hike_intro">Opening</label>
                                        <textarea class="form-control" id="hike_intro" name="hike_intro" rows="4">{{ $settings['hike_intro'] }}</textarea>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="hike_promotion">Promotion line (only when the designation changes)</label>
                                                <textarea class="form-control" id="hike_promotion" name="hike_promotion" rows="2">{{ $settings['hike_promotion'] }}</textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="hike_arrears">Arrears line (only when months were already paid)</label>
                                                <textarea class="form-control" id="hike_arrears" name="hike_arrears" rows="2">{{ $settings['hike_arrears'] }}</textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <x-forms.text fieldId="hike_table_intro" fieldLabel="Line above the salary table" fieldName="hike_table_intro" :fieldValue="$settings['hike_table_intro']"/>
                                    <div class="ol-help">The table follows: Monthly and Annual Gross, existing and revised, and the increase.</div>
                                    <div class="form-group">
                                        <label for="hike_closing">Closing</label>
                                        <textarea class="form-control" id="hike_closing" name="hike_closing" rows="5">{{ $settings['hike_closing'] }}</textarea>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-5">
                                            <div class="form-group">
                                                <label for="hike_signoff">Signed off by (one per line)</label>
                                                <textarea class="form-control" id="hike_signoff" name="hike_signoff" rows="3">{{ $settings['hike_signoff'] }}</textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-7">
                                            <x-forms.text fieldId="hike_ack_title" fieldLabel="Acknowledgement title" fieldName="hike_ack_title" :fieldValue="$settings['hike_ack_title']"/>
                                            <x-forms.text fieldId="hike_ack_text" fieldLabel="Acknowledgement text" fieldName="hike_ack_text" :fieldValue="$settings['hike_ack_text']"/>
                                        </div>
                                    </div>
                                    <div class="ol-help mt-1">Printed from HR &rarr; Salary Revisions once a revision is approved (a DRAFT mark until then).</div>
                                </div>
                            </div>
                        </div>

                        {{-- ============================== Footer & stamp ============================== --}}
                        <div class="tab-pane fade" id="tab-footer" role="tabpanel">
                            <div class="ol-section">
                                <h4>Footer (both letters)</h4>
                                <div class="ol-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <x-forms.text fieldId="footer_company" fieldLabel="Company name" fieldName="footer_company" :fieldValue="$settings['footer_company']"/>
                                        </div>
                                        <div class="col-md-6">
                                            <x-forms.text fieldId="footer_address" fieldLabel="Address" fieldName="footer_address" :fieldValue="$settings['footer_address']"/>
                                        </div>
                                        <div class="col-md-4">
                                            <x-forms.text fieldId="footer_phone" fieldLabel="Phone" fieldName="footer_phone" :fieldValue="$settings['footer_phone']"/>
                                        </div>
                                        <div class="col-md-4">
                                            <x-forms.text fieldId="footer_website" fieldLabel="Website" fieldName="footer_website" :fieldValue="$settings['footer_website']"/>
                                        </div>
                                        <div class="col-md-4">
                                            <x-forms.text fieldId="footer_email" fieldLabel="Email" fieldName="footer_email" :fieldValue="$settings['footer_email']"/>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="ol-section">
                                <h4>Signature &amp; stamp (both letters)</h4>
                                <div class="ol-body">
                                    <div class="form-group">
                                        <input type="hidden" name="show_stamp" value="0">
                                        <label class="mb-0"><input type="checkbox" name="show_stamp" value="1" class="mr-2" @checked($settings['show_stamp'])>
                                            Print the signature &amp; stamp above the sign-off</label>
                                    </div>
                                    <div class="row align-items-end mb-3">
                                        <div class="col-md-5">
                                            <label class="f-13">Stamp image (PNG or JPG; a transparent PNG looks best)</label>
                                            <input type="file" name="stamp_file" accept=".png,.jpg,.jpeg" class="form-control">
                                            @if ($settings['stamp_image'])
                                                <label class="f-12 mt-2 mb-0"><input type="checkbox" name="remove_stamp" value="1" class="mr-1"> Remove the uploaded image (use the company's own)</label>
                                            @endif
                                        </div>
                                        <div class="col-md-7 ol-stamp">
                                            @if ($stampUrl)
                                                <div class="f-12 text-dark-grey mb-1">{{ $settings['stamp_image'] ? 'Uploaded' : 'The company\'s own (public/img/sign and stamp.png)' }}:</div>
                                                <img src="{{ $stampUrl }}" alt="Stamp">
                                            @else
                                                <div class="f-12 text-dark-grey">No stamp image yet - upload one.</div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <x-slot name="action">
                <div class="w-100 border-top-grey">
                    <x-setting-form-actions>
                        <x-forms.button-primary id="save-offer-settings" class="mr-3" icon="check">@lang('app.save')</x-forms.button-primary>
                        <x-forms.button-secondary id="preview-offer-settings" class="mr-3" icon="eye">Preview Offer Letter</x-forms.button-secondary>
                        <x-forms.button-cancel id="reset-offer-settings" class="border-0">Restore the default wording</x-forms.button-cancel>
                    </x-setting-form-actions>
                </div>
            </x-slot>
        </x-setting-card>
    </div>
@endsection

@push('scripts')
<script>
    // The settings card is itself a form, so these fields sit in a <div> (a form inside a form is
    // dropped by the browser - that is why Preview did nothing): they are collected here.
    function letterFields(withFiles) {
        const data = [];
        $('#offer-settings-form').find('input, select, textarea').each(function () {
            if (!this.name || this.disabled) { return; }
            if (this.type === 'file') {
                if (withFiles && this.files.length) { data.push([this.name, this.files[0]]); }
                return;
            }
            if ((this.type === 'checkbox' || this.type === 'radio') && !this.checked) { return; }
            data.push([this.name, $(this).val()]);
        });
        return data;
    }

    $('#save-offer-settings').click(function () {
        const body = new FormData();
        letterFields(true).forEach(function (f) { body.append(f[0], f[1]); });
        const button = $(this).prop('disabled', true);

        $.ajax({url: "{{ route('offer-letter-settings.update') }}", type: 'POST', data: body, processData: false, contentType: false})
            .done(function (response) {
                Swal.fire({icon: response.status === 'success' ? 'success' : 'error', title: response.message,
                    timer: response.status === 'success' ? 1500 : undefined, showConfirmButton: response.status !== 'success'});
                if (response.status === 'success' && response.reload) { setTimeout(function () { window.location.reload(); }, 1200); }
            })
            .fail(function (xhr) {
                Swal.fire({icon: 'error', title: (xhr.responseJSON && xhr.responseJSON.message) || 'Could not save.'});
            })
            .always(function () { button.prop('disabled', false); });
    });

    // Preview the letter of the tab that is open (the Footer tab previews the offer letter).
    $('.ol-tabs a[data-toggle="tab"]').on('shown.bs.tab', function () {
        const which = $(this).data('preview') || 'offer';
        $('#preview-which').val(which);
        $('#preview-offer-settings').contents().last()[0].textContent = ' Preview '
            + ({relieving: 'Relieving', experience: 'Experience', hike: 'Hike'}[which] || 'Offer') + ' Letter';
    });

    $('#preview-offer-settings').click(function () {
        // A form of its own outside the card, posted to a new tab with the fields as they are now.
        const form = $('<form method="POST" target="_blank" style="display:none"></form>').attr('action', "{{ route('offer-letter-settings.preview') }}");
        letterFields(false).forEach(function (f) { $('<input type="hidden">').attr('name', f[0]).val(f[1]).appendTo(form); });
        form.appendTo('body');
        form[0].submit();
        form.remove();
    });

    // The wording the company started with, back on the form (saved only with Save).
    const offerDefaults = @json(array_diff_key($defaults, ['stamp_image' => 1]));
    $('#reset-offer-settings').click(function () {
        Swal.fire({
            title: 'Restore the default wording?',
            text: 'The form is filled with the original letters. Nothing is saved until you click Save.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Restore',
            customClass: {confirmButton: 'btn btn-primary mr-3', cancelButton: 'btn btn-secondary'},
            buttonsStyling: false
        }).then(function (r) {
            if (!r.isConfirmed) { return; }
            $.each(offerDefaults, function (key, value) {
                const $field = $('#offer-settings-form [name="' + key + '"]').not('[type=hidden]');
                if ($field.is(':checkbox')) { $field.prop('checked', !!parseInt(value)); }
                else { $field.val(value); }
            });
            $('#offer-settings-form select').each(function () { $(this).selectpicker && $(this).selectpicker('refresh'); });
        });
    });

    // Clicking a placeholder copies it.
    $('.ol-ph code').click(function () {
        navigator.clipboard && navigator.clipboard.writeText($(this).text());
    });
</script>
@endpush
