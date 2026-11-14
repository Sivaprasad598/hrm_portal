@extends('layouts.app')

@push('styles')
    <!-- for sortable content -->
    <link rel="stylesheet" href="{{ asset('vendor/css/jquery-ui.css') }}">

    <!-- to highlight html content -->
    <link rel="stylesheet" href="{{ asset('vendor/css/default.min.css') }}">
@endpush

@section('content')
    <!-- CONTENT WRAPPER START -->
    <div class="content-wrapper">
        <div class="row">
            <div class="col-lg-6">
                <div class="row">
                    <div class="col-sm-12">
                        <div class="card bg-white border-0 b-shadow-4">
                            <div class="card-body ">
                                <div class="col-md-12 mb-3">
                                    <div class="row">
                                        <div class="col-md-2 f-w-500">#</div>
                                        <div class="col-md-4 f-w-500">@lang('app.fields')</div>
                                        <div class="col-md-3 f-w-500">@lang('app.status')</div>
                                        <div class="col-md-3 f-w-500">@lang('app.required')</div>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <x-form id="editSettings" method="PUT">
                                        <div id="sortable">
                                            @foreach ($leadFormFields as $item)
                                                <div class="row py-2 pt-3 border-bottom">
                                                    <div class="col-md-2">
                                                        <span class="ui-icon ui-icon-arrowthick-2-n-s"></span>
                                                        <input type="hidden" name="sort_order[]"
                                                               value="{{ $item->id }}">
                                                    </div>
                                                    <div class="col-md-4">{{ $item->field_display_name}}</div>
                                                    <div class="col-md-3">
                                                        @if ($item->field_name != 'name')
                                                            <div class="custom-control custom-switch">
                                                                <input type="checkbox"
                                                                       class="custom-control-input change-setting"
                                                                       data-setting-id="{{ $item->id }}"
                                                                       @if ($item->status == 'active') checked @endif
                                                                       id="status_{{ $item->id }}">
                                                                <label class="custom-control-label f-14 cursor-pointer"
                                                                       for="status_{{ $item->id }}"></label>
                                                            </div>
                                                        @else
                                                            --
                                                        @endif
                                                    </div>
                                                    <div class="col-md-3">
                                                        @if ($item->field_name != 'name')
                                                            <div class="custom-control custom-switch">
                                                                <input type="checkbox"
                                                                       class="custom-control-input change-required"
                                                                       data-setting-id="{{ $item->id }}"
                                                                       @if ($item->required == 1) checked @endif
                                                                       id="required_{{ $item->id }}">
                                                                <label class="custom-control-label f-14 cursor-pointer"
                                                                       for="required_{{ $item->id }}"></label>
                                                            </div>
                                                        @else
                                                            <span class="text-success">✓</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach

                                        </div>
                                    </x-form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12 mt-4 mb-4">
                        <x-cards.data>
                            <p class="f-w-500">@lang('modules.lead.iframeSnippet')</p>
                            <code>
                                &lt;iframe src="{{ route('front.lead_form',[company()->hash]) }}"  frameborder="0" scrolling="yes"  style="display:block; width:100%; height:60vh;">&lt;/iframe&gt;
                            </code>
                        </x-cards.data>
                        <x-cards.data>
                            <p class="f-w-500">Share Direct link</p>
                            <p class="f-12"><a href="{{ route('front.lead_form', [company()->hash]).'?styled=1' }}"
                                               target="_blank">{{ route('front.lead_form', [company()->hash]).'?styled=1' }}</a>
                            </p>
                            <p class="f-12"><a
                                    href="{{ route('front.lead_form', [company()->hash]).'?styled=1&with_logo=1' }}"
                                    target="_blank">{{ route('front.lead_form', [company()->hash]).'?styled=1&with_logo=1' }}</a>
                            </p>
                        </x-cards.data>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <x-cards.data>
                    <h4>@lang('app.preview')</h4>
                    <iframe src="{{ route('front.lead_form', [company()->hash]) }}" id="previewIframe" width="100%"
                            onload="resizeIframe(this)" frameborder="0"></iframe>
                </x-cards.data>
                <br>

            </div>

        </div>
    </div>
    <!-- CONTENT WRAPPER END -->
@endsection

@push('scripts')
    <!-- for sortable content -->
    <script src="{{ asset('vendor/jquery/jquery-ui.min.js') }}"></script>

    <!-- to highlight html content -->
    <script src="{{ asset('vendor/jquery/highlight.min.js') }}"></script>

    <script>
        $(function () {
            $("#sortable").sortable({
                update: function (event, ui) {
                    var sortedValues = [];
                    $('input[name="sort_order[]"]').each(function (index, value) {
                        sortedValues[index] = $(this).val();
                    });
                    $.easyAjax({
                        url: "{{ route('lead-form.sortFields') }}",
                        type: "POST",
                        blockUI: true,
                        data: {
                            'sortedValues': sortedValues,
                            '_token': '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            var iframe = document.getElementById('previewIframe');
                            iframe.src = iframe.src;
                        }
                    })
                }
            });
        });

        $('.change-setting').change(function () {
            var id = $(this).data('setting-id');
            var sendEmail = $(this).is(':checked') ? 'active' : 'inactive';

            var url = '{{ route('lead-form.update', ':id') }}';
            url = url.replace(':id', id);
            $.easyAjax({
                url: url,
                type: "POST",
                blockUI: true,
                data: {
                    'id': id,
                    'status': sendEmail,
                    '_method': 'PUT',
                    '_token': '{{ csrf_token() }}'
                },
                success: function(response) {
                    var iframe = document.getElementById('previewIframe');
                    iframe.src = iframe.src;
                }
            })
        });

        $('.change-required').change(function () {
            var id = $(this).data('setting-id');
            var isRequired = $(this).is(':checked') ? 1 : 0;

            var url = '{{ route('lead-form.update', ':id') }}';
            url = url.replace(':id', id);
            $.easyAjax({
                url: url,
                type: "POST",
                blockUI: true,
                data: {
                    'id': id,
                    'required': isRequired,
                    '_method': 'PUT',
                    '_token': '{{ csrf_token() }}'
                },
                success: function(response) {
                    var iframe = document.getElementById('previewIframe');
                    iframe.src = iframe.src;
                }
            })
        });

        function resizeIframe(obj) {
            obj.style.height = obj.contentWindow.document.documentElement.scrollHeight + 50 + 'px';
        }

        init();
    </script>
@endpush
