<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\TrackLastActivity;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        // EnsureFrontendRequestsAreStateful must run first: it's what bootstraps
        // session/cookie handling for this route (mirrors what statefulApi()
        // does for the `api` group below) so a plain browser session — not just
        // a bearer token — can authorize a private channel subscription.
        ['middleware' => [EnsureFrontendRequestsAreStateful::class, 'auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        $middleware->statefulApi();

        $middleware->throttleApi('api');

        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_AWS_ELB
        );

        $middleware->append([
            AddSecurityHeaders::class,
            TrackLastActivity::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $throwable, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            $status = match (true) {
                $throwable instanceof ValidationException => Response::HTTP_UNPROCESSABLE_ENTITY,
                $throwable instanceof AuthenticationException => Response::HTTP_UNAUTHORIZED,
                $throwable instanceof AuthorizationException => Response::HTTP_FORBIDDEN,
                // Symfony HTTP exceptions (AccessDeniedHttpException, NotFoundHttpException,
                // TooManyRequestsHttpException, etc.) already carry the correct status —
                // e.g. Laravel's broadcaster throws AccessDeniedHttpException (403) for a
                // denied channel, which is neither AuthorizationException nor "unexpected".
                $throwable instanceof HttpExceptionInterface => $throwable->getStatusCode(),
                default => Response::HTTP_INTERNAL_SERVER_ERROR,
            };

            return response()->json([
                'success' => false,
                'message' => $throwable instanceof ValidationException
                    ? 'The given data was invalid.'
                    : ($throwable->getMessage() ?: Response::$statusTexts[$status]),
                'errors' => $throwable instanceof ValidationException
                    ? $throwable->errors()
                    : null,
            ], $status);
        });
    })->create();
