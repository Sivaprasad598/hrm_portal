<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

if(!file_exists('../.env')){
    $GLOBALS["error_type"] = "env-missing";
    include('error_install.php');
    exit(1);
}

if (version_compare(PHP_VERSION, '8.2.0') < 0){
    $GLOBALS["error_type"] = "php-version";
    include('error_install.php');
    exit(1);
}

define('LARAVEL_START', microtime(true));

/*
| Served without "/public" in the address (see ../.htaccess): the request came
| in as /hrm_portal/login but runs public/index.php. Tell the framework the app
| sits at the project folder, so it finds the route and every link it builds
| leaves "/public" out. A request that does name public/ is left alone.
*/
if (isset($_SERVER['SCRIPT_NAME'], $_SERVER['REQUEST_URI'])) {
    $publicUrlPath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');   // /hrm_portal/public
    $requestPath = strtok($_SERVER['REQUEST_URI'], '?');

    if (basename($publicUrlPath) === 'public'
        && $requestPath !== $publicUrlPath && !str_starts_with($requestPath, $publicUrlPath . '/')) {
        $_SERVER['SCRIPT_NAME'] = rtrim(dirname($publicUrlPath), '/\\') . '/index.php';   // /hrm_portal/index.php
        $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
    }

    unset($publicUrlPath, $requestPath);
}

/*
|--------------------------------------------------------------------------
| Check If Application Is Under Maintenance
|--------------------------------------------------------------------------
|
| If the application is maintenance / demo mode via the "down" command we
| will require this file so that any prerendered template can be shown
| instead of starting the framework, which could cause an exception.
|
*/

if (file_exists(__DIR__.'/../storage/framework/maintenance.php')) {
    require __DIR__.'/../storage/framework/maintenance.php';
}

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader for
| this application. We just need to utilize it! We'll simply require it
| into the script here so we don't need to manually load our classes.
|
*/

require __DIR__.'/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
|
| Once we have the application, we can handle the incoming request using
| the application's HTTP kernel. Then, we will send the response back
| to this client's browser, allowing them to enjoy our application.
|
*/

$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$response = tap($kernel->handle(
    $request = Request::capture()
))->send();

$kernel->terminate($request, $response);
