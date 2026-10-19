<?php

declare(strict_types=1);

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

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['role' => RequireRole::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Tradução das exceções de domínio para HTTP (o domínio não conhece HTTP).
        $exceptions->render(fn (InvalidCredentialsException $e): JsonResponse => response()->json(['message' => $e->getMessage()], 401));
        $exceptions->render(fn (NotFoundException $e): JsonResponse => response()->json(['message' => $e->getMessage()], 404));
        $exceptions->render(fn (ConflictException $e): JsonResponse => response()->json(['message' => $e->getMessage()], 409));
        $exceptions->render(fn (DomainException $e): JsonResponse => response()->json(['message' => $e->getMessage()], 422));
    })->create();
