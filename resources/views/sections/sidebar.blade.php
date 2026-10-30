<!-- SIDEBAR START -->
<aside class="{{ !user()->dark_theme ? 'sidebar-' . $appTheme->sidebar_theme : '' }}">
    <!-- MOBILE CLOSE SIDEBAR PANEL START -->
    <div class="mobile-close-sidebar-panel w-100 h-100" onclick="closeMobileMenu()" id="mobile_close_panel"></div>
    <!-- MOBILE CLOSE SIDEBAR PANEL END -->
    @php
        $userName = (session()->has('clientContact') && session('clientContact')) ? session('clientContact')->contact_name : user()->name;
    @endphp
    <!-- MAIN SIDEBAR START -->
    <div class="main-sidebar" id="mobile_menu_collapse">
        <!-- SIDEBAR BRAND START -->
        <div class="sidebar-brand-box {{ user()->dark_theme ? 'bg-dark' : '' }}">
            {{-- Just the greeting: no menu under it. Profile is the picture in the strip,
                 log out the power button in the top bar. --}}
            <div class="sidebar-brand d-flex align-items-center justify-content-between  w-100">

                @if (companyOrGlobalSetting()->sidebar_logo_style !== 'full')
                    <!-- SIDEBAR BRAND NAME START -->
                    {{-- A greeting for the person signed in, in place of the company name. --}}
                    <div class="sidebar-brand-name">
                        <h1 class="mb-0 f-16 f-w-500 text-white-shade mt-0" data-placement="bottom" data-toggle="tooltip"
                            data-original-title="{{ $userName }}">Hi, {{ $userName }}
                        </h1>
                    </div>
                    <!-- SIDEBAR BRAND NAME END -->
                    <!-- SIDEBAR BRAND LOGO START -->
                    <div class="sidebar-brand-logo">
                        <img src="{{ companyOrGlobalSetting()->logo_url }}">
                    </div>
                    <!-- SIDEBAR BRAND LOGO END -->
                @else
                    <!-- SIDEBAR BRAND NAME START -->
                    <div class="sidebar-brand-name">
                        <h1 class="mb-0 f-16 f-w-500 text-white-shade mt-0" data-placement="bottom"
                            data-toggle="tooltip" data-original-title="{{ $appName }}">
                            <img src="{{ companyOrGlobalSetting()->logo_url }}">
                        </h1>
                    </div>
                    <!-- SIDEBAR BRAND NAME END -->
                    <!-- SIDEBAR BRAND LOGO START -->
                    <div class="sidebar-brand-logo text-white-shade f-12">
                    </div>
                    <!-- SIDEBAR BRAND LOGO END -->
                @endif
            </div>
        </div>
        <!-- SIDEBAR BRAND END -->

        <!-- SIDEBAR MENU START -->
        <div class="sidebar-menu {{ user()->dark_theme ? 'bg-dark' : '' }}" id="sideMenuScroll">
            @include('sections.menu')
        </div>
        <!-- SIDEBAR MENU END -->
    </div>
    <!-- MAIN SIDEBAR END -->
    <!-- Sidebar Toggler -->
    <div
        class="text-center d-flex justify-content-between align-items-center position-fixed sidebarTogglerBox {{ user()->dark_theme ? 'bg-dark' : '' }}">
        <button class="border-0 d-lg-block d-none text-lightest font-weight-bold" id="sidebarToggle"></button>
        {{-- "Mobile App" button hidden (the code stays, to bring back). --}}
        @if (false && $showMobileAppButton)
            <a href="javascript:;"
               class="btn btn-outline-primary btn-sm f-10 px-2 py-1 pull-left border-0 d-flex align-items-center text-white mobile-app-btn"
               style="background-color: rgba(0,123,255,0.18); color: #ffffff;"
               data-toggle="modal"
               data-target="#mobileAppModal">
                <i class="fa fa-mobile mr-1" aria-hidden="true" style="line-height: 1; position: relative; top: -1px;"></i>
                <span>Mobile App</span>
            </a>
        @endif
        {{-- The HRM's own version (config app.hrm_version); version.txt stays as the base product's, for its updater. --}}
        <p class="mb-0 px-1 py-0 f-11 app-version">v{{ config('app.hrm_version', '1.0') }}</p>
    </div>
    <!-- Sidebar Toggler -->

    @include('sections.raise-support-ticket-modal')
    @if ($showMobileAppButton)
        @include('sections.mobile-app-modal')
    @endif
</aside>
<!-- SIDEBAR END -->

<style>
    .sidebarTogglerBox p.app-version { color: #ffffff !important; background: transparent !important; font-weight: 500; }

    .sidebarTogglerBox .mobile-app-btn:hover {
        background-color: #007bff !important;
        color: #ffffff !important;
    }

    .sidebarTogglerBox .mobile-app-btn:hover i {
        color: #ffffff !important;
    }
</style>

<script>
    $(document).ready(function() {

        $('.invite-member').click(function() {
            const url = "{{ route('employees.invite_member') }}";
            $(MODAL_LG + ' ' + MODAL_HEADING).html('...');
            $.ajaxModal(MODAL_LG, url);
        });

    });
</script>
