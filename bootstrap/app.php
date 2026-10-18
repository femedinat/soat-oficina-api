<?php

declare(strict_types=1);

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Oficina\Customer\Domain\Exception\VehicleNotFoundException;
use Oficina\Shared\Domain\Exception\DomainException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (VehicleNotFoundException $exception, Request $request): ?JsonResponse {
            if ($request->is('api/*') || $request->expectsJson()) {
                return new JsonResponse([
                    'message' => $exception->getMessage(),
                ], Response::HTTP_NOT_FOUND);
            }

            return null;
        });

        $exceptions->render(function (DomainException $exception, Request $request): ?JsonResponse {
            if ($request->is('api/*') || $request->expectsJson()) {
                return new JsonResponse([
                    'message' => $exception->getMessage(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return null;
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request): ?JsonResponse {
            if ($request->is('api/*') || $request->expectsJson()) {
                return new JsonResponse([
                    'message' => 'Não autenticado.',
                ], Response::HTTP_UNAUTHORIZED);
            }

            return null;
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request): ?JsonResponse {
            if ($request->is('api/*') || $request->expectsJson()) {
                $previous = $exception->getPrevious();
                if ($previous instanceof VehicleNotFoundException) {
                    return new JsonResponse([
                        'message' => $previous->getMessage(),
                    ], Response::HTTP_NOT_FOUND);
                }

                return new JsonResponse([
                    'message' => 'Recurso ou rota não encontrada.',
                ], Response::HTTP_NOT_FOUND);
            }

            return null;
        });

        $exceptions->render(function (MethodNotAllowedHttpException $exception, Request $request): ?JsonResponse {
            if ($request->is('api/*') || $request->expectsJson()) {
                return new JsonResponse([
                    'message' => 'Método HTTP não permitido para esta rota.',
                ], Response::HTTP_METHOD_NOT_ALLOWED);
            }

            return null;
        });
    })->create();
