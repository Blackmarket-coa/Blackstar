<?php

namespace App\Exceptions;

use Fleetbase\Exceptions\Handler as ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler as FrameworkHandler;

class Handler extends ExceptionHandler
{
    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Render straight through the framework handler. Fleetbase's render()
     * intercepts AuthenticationException and NotFoundHttpException and
     * flattens both into generic 400 responses; status codes are contract
     * for API clients (and for this app's own feature tests), so 401/404
     * must survive. Reporting behavior from the Fleetbase handler is kept.
     */
    public function render($request, \Throwable $e)
    {
        return FrameworkHandler::render($request, $e);
    }
}
