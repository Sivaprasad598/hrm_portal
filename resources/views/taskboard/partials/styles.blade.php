{{-- Task board (Kanban) layout - shared by the Task Board page and the project Task Board tab.
     Overrides the fixed 340px columns from main.css (resources/scss/task-board.scss). --}}
<style>
    .w-task-board-box:after {
        display: none;
    }

    /* The board fills the rest of the viewport (height set by fitTaskBoard below);
       columns share the width equally and stretch to the full height */
    .w-task-board-box .w-task-board-panel {
        gap: 14px;
        overflow-x: auto;
        overflow-y: hidden;
        padding-bottom: 10px;
        scroll-snap-type: x proximity;
        align-items: stretch;
        min-height: 360px;
    }

    .w-task-board-box .w-task-board-panel .board-panel {
        flex: 1 1 0;
        width: auto;
        min-width: 230px;
        max-width: none;
        margin: 0 !important;
        white-space: normal;
        scroll-snap-align: start;
        border-top: 3px solid var(--column-color, #d0d5dd) !important;
        display: flex;
        flex-direction: column;
    }

    .w-task-board-box .w-task-board-panel .minimized {
        flex: 0 0 52px;
        margin: 0 !important;
        border-top: 3px solid var(--column-color, #d0d5dd) !important;
    }

    /* Column header: colour dot, name (ellipsis + tooltip), count, actions */
    .board-panel .b-p-header {
        margin: 12px 10px 8px 12px !important;
        align-items: center;
        gap: 8px;
    }

    .board-panel .b-p-title {
        flex: 1 1 auto;
        min-width: 0;
        display: flex;
        align-items: center;
        font-size: 14px;
    }

    .board-panel .b-p-title .b-p-name {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .board-panel .b-p-header .b-p-badge {
        border-radius: 10px;
        padding: 0 8px !important;
        line-height: 20px !important;
        font-size: 12px !important;
        flex-shrink: 0;
    }

    .board-panel .b-p-header .collapse-column {
        padding: 4px;
    }

    /* Column body takes the remaining column height and scrolls its own cards */
    .w-task-board-box .w-task-board-panel .board-panel .b-p-body {
        flex: 1 1 auto;
        height: auto;
        min-height: 0;
        max-height: none;
        overflow-y: auto;
        padding: 0 8px 8px;
    }

    .board-panel .b-p-tasks {
        min-height: 140px;
    }

    /* Empty column: slim dashed drop target instead of a big white card */
    .board-panel .no-task-card {
        border: 1px dashed #c8ced6 !important;
        box-shadow: none !important;
        background: transparent !important;
    }

    .board-panel .no-task-card .card-body {
        padding: 6px 10px;
    }

    .board-panel .no-task-card .py-3 {
        padding-top: 6px !important;
        padding-bottom: 6px !important;
    }

    .board-panel .no-task-card a,
    .board-panel .no-task-card .f-15 {
        font-size: 13px !important;
    }

    .board-panel .no-task-card .mt-4 {
        margin-top: 6px !important;
    }

    /* Cards */
    .board-panel .task-card {
        transition: box-shadow .15s ease;
    }

    .board-panel .task-card:hover {
        box-shadow: 0 4px 14px rgba(16, 24, 40, .10) !important;
    }

    /* Title and task code side by side without running into each other */
    .board-panel .task-card .card-body > .d-flex:first-child {
        gap: 8px;
    }

    .board-panel .task-card .card-body > .d-flex:first-child > p {
        white-space: nowrap;
        flex-shrink: 0;
    }

    .board-panel .task-card .card-body > .d-flex:first-child > a {
        min-width: 0;
        word-break: break-word;
    }

    .board-panel .task-card .avatar-img + .avatar-img {
        margin-left: -6px;
    }

    .board-panel .task-card .avatar-img img {
        border: 2px solid #fff;
    }

    .taskboard-hint {
        font-size: 12px;
    }

    /* Tablets: a bit narrower */
    @media (max-width: 1199.98px) {
        .w-task-board-box .w-task-board-panel .board-panel {
            flex: 0 0 260px;
            min-width: 260px;
        }
    }

    /* Phones: one column per screen, swipe between columns */
    @media (max-width: 767.98px) {
        .w-task-board-box {
            padding-left: 12px !important;
            padding-right: 12px !important;
        }

        .w-task-board-box .w-task-board-panel {
            scroll-snap-type: x mandatory;
        }

        .w-task-board-box .w-task-board-panel .board-panel {
            flex: 0 0 85vw;
            min-width: 0;
            max-width: 85vw;
        }

        .w-task-board-box .action-bar .btn-group {
            width: 100%;
        }

        .w-task-board-box .action-bar .btn-group .btn {
            flex: 1 1 0;
        }
    }

    body.dark-theme .board-panel .no-task-card {
        border-color: #3b4252 !important;
    }

    body.dark-theme .board-panel .task-card .avatar-img img {
        border-color: #1f2937;
    }
</style>

<script>
    /* Size the board to the rest of the viewport, whatever sits above it (filters, buttons, hint) */
    window.fitTaskBoard = function () {
        document.querySelectorAll('.w-task-board-panel').forEach(function (panel) {
            var box = panel.closest('.w-task-board-box');
            var boxPadding = box ? parseFloat(getComputedStyle(box).paddingBottom) || 0 : 0;
            var top = panel.getBoundingClientRect().top;
            panel.style.height = Math.max(window.innerHeight - top - boxPadding - 8, 360) + 'px';
        });
    };

    window.addEventListener('resize', function () {
        window.fitTaskBoard();
    });
</script>
