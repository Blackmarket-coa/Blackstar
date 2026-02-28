<?php

declare(strict_types=1);

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$handleRequest = static function () use ($kernel): void {
    $request = Request::capture();

    $response = $kernel->handle($request);

    $response->send();

    $kernel->terminate($request, $response);
};

while (frankenphp_handle_request($handleRequest)) {
    // Handle the next request using the same long-lived worker process.
}
