<!-- PAGE TITLE START -->
<div {{ $attributes->merge(['class' => 'page-title']) }}>
    <div class="page-heading">
        <h2 class="mb-0 pr-3 text-dark f-18 font-weight-bold d-flex align-items-center">
            <span class="d-inline-block text-truncate mw-300">{{ $pageTitle }}</span>

            <span class="text-lightest f-12 f-w-500 mx-2 mw-250 text-truncate">
                <a href="{{ route('dashboard') }}" class="text-lightest">@lang('app.menu.home')</a> &bull;
                @php
                    $link = '';
                @endphp

                @for ($i = 1; $i <= count(Request::segments()); $i++)
                    @if (($i < count(Request::segments())) && ($i > 0))
                        @php $link .= '/' . Request::segment($i); @endphp

                        @if (Request::segment($i) != 'account')
                            @php
                                $langKey = 'app.'.str(Request::segment($i))->camel();

                                if (!Lang::has($langKey)) {
                                    $langKey = str($langKey)->replace('app.', 'app.menu.')->__toString();
                                }
                                $segmentText = Lang::has($langKey) ? __($langKey) : ucwords(str_replace('-', ' ', Request::segment($i)));
                                // url() keeps the installation's own folder (localhost/hrm_portal/public/...);
                                // a hand-built "/public/..." dropped it and opened a blank page.
                                $segmentLink = url($link);
                            @endphp

                            @if (in_array(Request::segment($i), App\Enums\NonClickableSegments::getValues()))
                                {{ $segmentText }} &bull;
                            @elseif ($i === count(Request::segments()) - 1 && $segmentText === $pageTitle)
                                {{ $segmentText }}
                            @else
                                <a href="{{ $segmentLink }}" class="text-lightest">
                                    {{ $segmentText }}
                                </a> &bull;
                            @endif
                        @endif
                    @else
                        @php
                            $segmentCount = count(Request::segments());
                            $prevSegmentText = null;
                            for ($j = $segmentCount - 1; $j >= 1; $j--) {
                                if (Request::segment($j) === 'account') {
                                    continue;
                                }
                                $prevKey = 'app.'.str(Request::segment($j))->camel();
                                if (!Lang::has($prevKey)) {
                                    $prevKey = str($prevKey)->replace('app.', 'app.menu.')->__toString();
                                }
                                $prevSegmentText = Lang::has($prevKey) ? __($prevKey) : ucwords(str_replace('-', ' ', Request::segment($j)));
                                break;
                            }
                        @endphp
                        @if ($prevSegmentText !== $pageTitle)
                            {{ $pageTitle }}
                        @endif
                    @endif
                @endfor
            </span>
        </h2>
    </div>
</div>
<!-- PAGE TITLE END -->
