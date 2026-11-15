@extends('layouts.app')

@push('styles')
    <style>
        /* The slip is a page of its own; let it keep its own styling and sit
           on the card rather than inheriting the theme's table rules. */
        .payslip-frame { border: 0; width: 100%; min-height: 80vh; background: #fff; }
        .ms-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        body.epic .ms-actions > a { height: 38px; display: inline-flex; align-items: center; padding: 0 14px !important;
            border-radius: 8px !important; white-space: nowrap; text-decoration: none; }
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

                {{-- Both actions together at the right, the same size --}}
                <div class="ms-actions ml-auto">
                    <a href="{{ route('my-payslips.index') }}" class="btn-secondary rounded f-14">
                        <i class="fa fa-arrow-left mr-1"></i> All payslips
                    </a>
                    <a href="{{ route('my-payslips.pdf', $payslip->id) }}" class="btn-primary rounded f-14">
                        <i class="fa fa-download mr-1"></i> Download PDF
                    </a>
                </div>
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
