<style>
    :root {
        /* For Logged in user take header color a app theme*/
        /* For public pages use company specific header color example invoice,estimate public page*/
        /* For all other pages use like auth use global setting header*/
        --header_color: @if(isset($appTheme)) {{ $appTheme->header_color}} @elseif(isset($company)) {{$company->header_color}} @else {{ global_setting()->header_color}} @endif;
    }

    .btn-primary,
    .btn-primary.disabled:hover,
    .btn-primary:disabled:hover {
        background-color: var(--header_color) !important;
        border: 1px solid var(--header_color) !important;
    }

    .text-primary {
        color: var(--header_color) !important;
    }

    .bg-primary {
        background: var(--header_color) !important;
    }

    .datepicker table tr td, .datepicker table tr th {
        font-size: 14px;
    }

    .project-header .project-menu .p-sub-menu.active:after,
    .project-header .project-menu .p-sub-menu::after,
    .qs-current, .datepicker table tr td.active.active {
        background: var(--header_color) !important;
        text-shadow: none;
        border-color: var(--header_color) !important;
    }

    .sidebar-brand-box .sidebar-brand-dropdown a.dropdown-item:hover,
    .dropdown-item.active,
    .close-task-detail {
        background-color: var(--header_color) !important;
    }

    .pagination .page-item.active .page-link,
    .custom-control-input:checked ~ .custom-control-label::before {
        background-color: var(--header_color) !important;
        border-color: var(--header_color) !important;
    }

    .close-task-detail span {
        border: 1px solid var(--header_color) !important;
    }

    .tabs .nav .nav-link.active,
    .tabs .nav .nav-item:hover {
        border-bottom: 3px solid var(--header_color) !important;
    }

    .sidebar-light .sidebar-menu li .nav-item:focus,
    .sidebar-light .sidebar-menu li .nav-item:hover,
    .sidebar-light .sidebar-menu li .accordionItemContent a:hover {
        color: var(--header_color) !important;
    }

    .sidebar-light .accordionItem a.active {
        color: var(--header_color) !important;
    }

    .menu-item-count, .unread-notifications-count, .active-timer-count {
        background-color: var(--header_color) !important;
    }

    .dropdown-item.active .text-dark-grey {
        color: #ffffff;
    }

    /* Sidebar: white background, black text/icons, regardless of the
       admin "Sidebar Theme" setting or a user's personal dark-theme toggle. */
    .main-sidebar,
    .sidebar-dark .main-sidebar,
    .sidebar-light .main-sidebar,
    body.dark-theme .main-sidebar,
    .sidebar-brand-box,
    .sidebar-dark .sidebar-brand-box,
    .sidebar-light .sidebar-brand-box,
    body.dark-theme .sidebar-brand-box,
    .sidebar-menu,
    .sidebar-dark .sidebar-menu,
    .sidebar-light .sidebar-menu,
    body.dark-theme .sidebar-menu,
    .sidebarTogglerBox,
    .sidebar-dark .sidebarTogglerBox,
    .sidebar-light .sidebarTogglerBox,
    body.dark-theme .sidebarTogglerBox {
        background-color: #ffffff !important;
        border-right: 1px solid #e8eef3 !important;
    }

    .main-sidebar .nav-item,
    .main-sidebar .text-lightest,
    .main-sidebar .text-white-shade,
    .main-sidebar .sidebar-brand-name h1,
    .main-sidebar .sidebar-brand-logo,
    .main-sidebar .pro-name p,
    .sidebarTogglerBox,
    .sidebarTogglerBox p {
        color: #000000 !important;
    }

    .main-sidebar svg {
        fill: #000000 !important;
    }

    .main-sidebar .nav-item:hover,
    .main-sidebar .nav-item:focus,
    .main-sidebar .accordionItem a.active,
    .main-sidebar .accordionItemContent a:hover,
    .sidebar-menu li .nav-item:hover,
    .sidebar-menu li .nav-item:focus,
    .sidebar-menu li .accordionItemContent a:hover,
    .sidebar-dark .sidebar-menu li .nav-item:hover,
    .sidebar-dark .sidebar-menu li .nav-item:focus,
    .sidebar-dark .sidebar-menu li .accordionItemContent a:hover,
    .sidebar-dark .accordionItem a.active,
    .sidebar-light .sidebar-menu li .nav-item:hover,
    .sidebar-light .sidebar-menu li .nav-item:focus,
    .sidebar-light .sidebar-menu li .accordionItemContent a:hover,
    .sidebar-light .accordionItem a.active {
        color: #000000 !important;
    }

    /* Page tabs (Profile | Deals | Notes | Meetings): one underline only. The theme underlines the active
       tab with a border; the base stylesheet also drew a 3px bar under it, which gave two lines. */
    .project-menu .p-sub-menu::after,
    .project-menu .p-sub-menu:hover::after,
    .project-menu .p-sub-menu.active::after,
    .project-menu .p-sub-menu.active:hover::after {
        display: none !important;
    }

</style>
