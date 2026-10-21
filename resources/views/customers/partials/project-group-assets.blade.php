{{-- Styles and toggle for the collapsible "Project: …" meeting sections (project-group-header.blade.php) --}}
<style>
    .project-meetings-group .project-group-header { background: #eef4fc; cursor: pointer; }
    .project-meetings-group .project-group-header .group-chevron { transition: transform .2s; width: 18px; }
    .project-meetings-group .project-group-header.collapsed .group-chevron { transform: rotate(-90deg); }
    .project-meetings-group .project-group-header .group-toggle i { transition: transform .2s; }
    .project-meetings-group .project-group-header.collapsed .group-toggle i { transform: rotate(180deg); }
</style>

<script>
    // Toggle button (or a click anywhere on the header): show or hide that project's meetings only;
    // every section opens and closes on its own
    $('body').off('click', '.project-group-header').on('click', '.project-group-header', function () {
        var header = $(this);
        var body = header.next('.project-group-body');
        var open = header.hasClass('collapsed');

        header.toggleClass('collapsed', !open);
        header.find('.group-toggle').attr('aria-expanded', open ? 'true' : 'false');
        body.stop(true, true)[open ? 'slideDown' : 'slideUp'](200);
    });
</script>
