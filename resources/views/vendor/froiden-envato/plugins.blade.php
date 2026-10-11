<style>
@keyframes glow {
    0% {
        /* box-shadow: 0 4px 16px 0 rgba(255,193,7,0.1), 0 0 0 0 rgba(255,193,7,0.4); */
        transform: scale(1.00);
    }
    100% {
        /* box-shadow: 0 6px 24px 0 rgba(255,193,7,0.2), 0 0 20px 2px rgba(255,193,7,0.2); */
        transform: scale(1.05);
    }
}


.universal-highlight:hover {
    animation: bounce-subtle 0.6s ease-in-out infinite;
}
</style>

<div class="bg-gradient-to-b from-gray-50 to-white py-14">
    <!-- Section Separator -->
    <div class="container mx-auto px-2 max-w-5xl mb-10">
        <div class="flex items-center justify-center">
            <div class="flex-grow border-t border-gray-200"></div>
            <div class="mx-4 bg-white px-8 py-3 rounded-full shadow border border-gray-100">
                <div class="flex items-center space-x-2">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-4m-5 0H3m2 0h4M9 7h6m-6 4h6m-6 4h6"/>
                    </svg>
                    <span class="text-base font-semibold text-gray-700">Premium Add-ons Available</span>
                </div>
            </div>
            <div class="flex-grow border-t border-gray-200"></div>
        </div>
        <p class="text-center text-sm text-gray-500 mt-4">
            Explore additional modules to extend your Arowana HR Portal functionality
        </p>
    </div>

    <div class="container mx-auto px-2 max-w-5xl">
        <!-- Redesigned: Spacious, Responsive Cards with Highlighted Universal Bundle -->
        <div class="mb-12">
            @php
                $modules = collect(\Froiden\Envato\Functions\EnvatoUpdate::plugins())->sortByDesc('number_of_sales');
                $universal = $modules->first(function($m) {
                    return stripos($m['product_name'], 'Universal Modules Bundle') !== false;
                });
                $otherModules = $modules->filter(function($m) {
                    return stripos($m['product_name'], 'Universal Modules Bundle') === false;
                });
            @endphp
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 items-stretch">
                @if($universal)
                    @php $module = $universal; $isUniversal = true; @endphp
                    <a href="{{ $module['product_link'] }}" target="_blank" class="block h-full">
                        <div class="
                            bg-gradient-to-br from-amber-50 to-white rounded-lg shadow-md hover:shadow-xl transition-all duration-300 flex flex-col items-center p-4 relative group cursor-pointer h-full
                            ring-2 ring-amber-400 ring-offset-1 scale-102 z-10 universal-highlight
                            "
                            style="
                                animation: glow 2s ease-in-out infinite alternate;
                            "
                        >
                            <!-- Recommended Badge -->
                            <div class="absolute -top-1 left-2 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white text-xs font-medium px-2.5 py-1 rounded-md shadow-md z-20">
                                <div class="flex items-center gap-1">
                                    <svg class="w-2.5 h-2.5 fill-current" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                    </svg>
                                    <span class="text-xs">RECOMMENDED</span>
                                </div>
                            </div>

                            <!-- Price Badge -->
                            <span class="absolute top-2 right-2 bg-blue-600 text-white text-xs font-semibold px-2 py-1 rounded-full shadow">
                                ${{ $module['price'] }}
                            </span>

                            <!-- Trending Badge -->
                            @if($module['trending'])
                                <span class="absolute top-2 left-2 bg-red-500 text-white text-xs font-semibold px-2 py-1 rounded-full shadow">
                                    Trending
                                </span>
                            @endif

                            <!-- Thumbnail -->
                            <div class="bg-gray-100 rounded-md shadow p-1 mb-3 flex items-center justify-center">
                                <img src="{{ $module['product_thumbnail'] }}" alt="{{ $module['product_name'] }}"
                                     class="w-[48px] h-[48px] object-cover rounded-sm" />
                            </div>

                            <!-- Content -->
                            <h3 class="text-sm font-bold text-amber-600 text-center mb-2 leading-tight">
                                {{ ucwords(str_replace(['for arowana hr portal saas','for arowana hr portal', 'arowana hr portal', 'crm','advanced reporting projects'], '', strtolower($module['product_name']))) }}

                            </h3>

                            <!-- Version and Sales -->
                            <div class="flex items-center gap-2 mb-2">
                                <span class="text-xs text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded text-xs">
                                    v{{ $module['version'] }}
                                </span>
                                <span class="text-xs text-gray-500 bg-green-100 px-1.5 py-0.5 rounded text-xs">
                                    {{ $module['number_of_sales'] }} sales
                                </span>
                            </div>

                            <!-- Rating -->
                            @if($module['rating_count'] > 0)
                                <div class="flex items-center mb-2">
                                    <div class="flex items-center">
                                        @for($i = 1; $i <= 5; $i++)
                                            <svg class="w-3 h-3 {{ $i <= $module['rating'] ? 'text-yellow-400' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                            </svg>
                                        @endfor
                                    </div>
                                    <span class="text-xs text-gray-500 ml-1">({{ $module['rating_count'] }})</span>
                                </div>
                            @else
                                <div class="flex items-center mb-2">
                                    <span class="text-xs text-gray-400">No ratings yet</span>
                                </div>
                            @endif

                            <!-- Summary -->
                            @if($module['summary'])
                                <p class="text-xs text-gray-600 text-center mb-3 line-clamp-2">{{ $module['summary'] }}</p>
                            @endif

                            <!-- Footer -->
                            <div class="flex items-center justify-center w-full mt-auto">
                                <span class="text-xs font-medium text-amber-600">
                                    Best Value! →
                                </span>
                            </div>
                        </div>
                    </a>
                @endif

                @foreach($otherModules as $module)
                    @php $isUniversal = false; @endphp
                    <a href="{{ $module['product_link'] }}" target="_blank" class="block h-full">
                        <div class="
                            bg-white rounded-lg shadow-md hover:shadow-xl transition-all duration-300 flex flex-col items-center p-4 relative group cursor-pointer h-full
                            hover:ring-2 hover:ring-blue-200
                            "
                        >
                            <!-- Price Badge -->
                            <span class="absolute top-2 right-2 bg-blue-600 text-white text-xs font-semibold px-2 py-1 rounded-full shadow">
                                ${{ $module['price'] }}
                            </span>

                            <!-- Trending Badge -->
                            @if($module['trending'])
                                <span class="absolute top-2 left-2 bg-red-500 text-white text-xs font-semibold px-2 py-1 rounded-full shadow">
                                    Trending
                                </span>
                            @endif

                            <!-- Thumbnail -->
                            <div class="bg-gray-100 rounded-md shadow p-1 mb-3 flex items-center justify-center">
                                <img src="{{ $module['product_thumbnail'] }}" alt="{{ $module['product_name'] }}"
                                     class="w-[48px] h-[48px] object-cover rounded-sm" />
                            </div>

                            <!-- Content -->
                            <h3 class="text-sm font-bold text-gray-900 text-center mb-2 leading-tight">
                                {{ ucwords(str_replace(['for arowana hr portal crm', 'for arowana hr portal','- advanced reporting  projects'], '', strtolower($module['product_name']))) }}
                            </h3>

                            <!-- Version and Sales -->
                            <div class="flex items-center gap-2 mb-2">
                                <span class="text-xs text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded text-xs">
                                    v{{ $module['version'] }}
                                </span>
                                <span class="text-xs text-gray-500 bg-green-100 px-1.5 py-0.5 rounded text-xs">
                                    {{ $module['number_of_sales'] }} sales
                                </span>
                            </div>

                            <!-- Rating -->
                            @if($module['rating_count'] > 0)
                                <div class="flex items-center mb-2">
                                    <div class="flex items-center">
                                        @for($i = 1; $i <= 5; $i++)
                                            <svg class="w-3 h-3 {{ $i <= $module['rating'] ? 'text-yellow-400' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                            </svg>
                                        @endfor
                                    </div>
                                    <span class="text-xs text-gray-500 ml-1">({{ $module['rating_count'] }})</span>
                                </div>
                            @else
                                <div class="flex items-center mb-2">
                                    <span class="text-xs text-gray-400">No ratings yet</span>
                                </div>
                            @endif

                            <!-- Summary -->
                            @if($module['summary'])
                                <p class="text-xs text-gray-600 text-center mb-3 line-clamp-2">{{ $module['summary'] }}</p>
                            @endif

                            <!-- Footer -->
                            <div class="flex items-center justify-center w-full mt-auto">
                                <span class="text-xs font-medium text-gray-600">
                                    Learn more →
                                </span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>

    </div>
</div>