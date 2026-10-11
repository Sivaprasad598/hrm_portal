<?php
// This page moved behind the application's login.
// It is served at /account/legacy/{page}; direct access here is refused
// because it exposed salary and employee data to anyone with the URL.
http_response_code(403);
header('Content-Type: text/plain; charset=UTF-8');
exit("This page is no longer available here. Open it from inside the HR portal, after signing in.
");
