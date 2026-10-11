<div class="app-launcher-wrap d-none d-md-flex align-items-center">
    <a href="javascript:;" id="app-launcher-toggle" class="header-icon-box" data-toggle="tooltip"
       data-placement="bottom" title="@lang('app.menu.appLauncher')">
        <i class="fa fa-th f-16 text-dark-grey"></i>
    </a>

    <div id="app-launcher-popup" class="app-launcher-popup">
        @include('sections.app-launcher')
    </div>
</div>

<style>
    .app-launcher-wrap {
        position: relative;
        margin-left: 14px;
    }

    .app-launcher-popup {
        position: fixed;
        top: 63px;
        left: 50%;
        width: 1140px;
        max-width: calc(100vw - 32px);
        max-height: calc(100vh - 90px);
        overflow-y: auto;
        z-index: 1050;
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transform: translate(-50%, -12px);
        transition: opacity 0.2s ease, transform 0.2s ease, visibility 0.2s;
    }

    .app-launcher-popup.show {
        opacity: 1;
        visibility: visible;
        pointer-events: auto;
        transform: translate(-50%, 0);
    }
</style>

<script>
    $(document).ready(function () {
        $('#app-launcher-toggle').on('click', function (e) {
            e.stopPropagation();
            $('#app-launcher-popup').toggleClass('show');
        });

        $('#app-launcher-popup').on('click', function (e) {
            e.stopPropagation();
        });

        $(document).on('click', function () {
            $('#app-launcher-popup').removeClass('show');
        });

        $(document).on('keyup', function (e) {
            if (e.key === 'Escape' || e.keyCode === 27) {
                $('#app-launcher-popup').removeClass('show');
            }
        });
    });
</script>
