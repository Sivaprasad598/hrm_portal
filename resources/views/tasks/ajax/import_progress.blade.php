@include('import.process-form', [
    'headingTitle' => __('app.importExcel') . ' ' . __('app.menu.tasks'),
    'processRoute' => route('tasks.import.process'),
    'backRoute' => route('tasks.index'),
    'backButtonText' => __('app.backToTasks'),
])
