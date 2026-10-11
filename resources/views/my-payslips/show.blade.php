@extends('layouts.app')

@push('styles')
    <style>
        /* The slip is a page of its own; let it keep its own styling and sit
           on the card rather than inheriting the theme's table rules. */
        .payslip-frame { border: 0; width: 100%; min-height: 80vh; background: #fff; }
    </style>
@endpush

@section('content')
    <div class="content-wrapper">
        <div class="card bg-white border-0 b-shadow-4 mb-3">
            <div class="card-header bg-white border-bottom d-flex align-items-center flex-wrap p-3">
                <div class="mr-auto">
                    <h6 class="mb-0 f-14">{{ $payslip->period }}</h6>
                    <p class="mb-0 f-12 text-lightest">{{ $payslip->payslip_number }}</p>
                </div>

                <a href="{{ route('my-payslips.index') }}" class="btn btn-sm btn-outline-secondary mr-2">
                    <i class="fa fa-arrow-left mr-1"></i> All payslips
                </a>
                <a href="{{ route('my-payslips.pdf', $payslip->id) }}" class="btn btn-sm btn-primary">
                    <i class="fa fa-download mr-1"></i> Download PDF
                </a>
            </div>

            <div class="card-body p-0">
                {{-- The slip is written straight into a frame of its own, so its
                     styling cannot fight with the page around it. --}}
                <iframe class="payslip-frame" id="payslip-frame" title="{{ $payslip->period }} payslip"></iframe>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            var frame = document.getElementById('payslip-frame');
            var slip = @json($html);

            frame.srcdoc = slip;

            // Grow the frame to whatever the slip needs, so there is no scrollbar
            // inside a scrollbar.
            frame.addEventListener('load', function () {
                try {
                    var height = frame.contentDocument.body.scrollHeight;

                    if (height > 200) {
                        frame.style.height = (height + 40) + 'px';
                    }
                } catch (error) {
                    // Different-origin frames cannot be measured; the default height stands.
                }
            });
        });
    </script>
@endpush
