<?php

declare(strict_types=1);

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Oficina\Identity\Domain\Exception\InvalidCredentialsException;
use Oficina\Identity\Interfaces\Http\RequireRole;
use Oficina\Shared\Domain\Exception\ConflictException;
use Oficina\Shared\Domain\Exception\DomainException;
use Oficina\Shared\Domain\Exception\NotFoundException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['role' => RequireRole::class]);

        // API sem sessão nem tela de login: visitante sem token recebe 401 JSON, nunca redirect (evitava 500 "Route [login] not defined").
        $middleware->redirectGuestsTo(static fn (Request $request): ?string => $request->is('api/*') ? null : '/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Tradução das exceções de domínio para HTTP (o domínio não conhece HTTP).
        $exceptions->render(fn (InvalidCredentialsException $e): JsonResponse => response()->json(['message' => $e->getMessage()], Response::HTTP_UNAUTHORIZED));
        $exceptions->render(fn (NotFoundException $e): JsonResponse => response()->json(['message' => $e->getMessage()], Response::HTTP_NOT_FOUND));
        $exceptions->render(fn (ConflictException $e): JsonResponse => response()->json(['message' => $e->getMessage()], Response::HTTP_CONFLICT));
        $exceptions->render(fn (DomainException $e): JsonResponse => response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY));

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
                if ($previous instanceof NotFoundException) {
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
