<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class ApiExceptionHandler
{
    public static function register(Exceptions $exceptions): void
    {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => self::shouldRenderJson($request),
        );

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! self::shouldRenderJson($request)) {
                return null;
            }

            return self::errorResponse('Error de validacion.', 422, $e->errors());
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! self::shouldRenderJson($request)) {
                return null;
            }

            return self::errorResponse('No autenticado.', 401);
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if (! self::shouldRenderJson($request)) {
                return null;
            }

            return self::errorResponse('No autorizado para realizar esta accion.', 403);
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if (! self::shouldRenderJson($request)) {
                return null;
            }

            return self::errorResponse('Recurso no encontrado.', 404);
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! self::shouldRenderJson($request)) {
                return null;
            }

            return self::errorResponse('La URL solicitada no existe.', 404, [
                'path' => $request->path(),
                'request_uri' => $request->getRequestUri(),
            ]);
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            if (! self::shouldRenderJson($request)) {
                return null;
            }

            return self::errorResponse('Metodo HTTP no permitido para esta URL.', 405);
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if (! self::shouldRenderJson($request)) {
                return null;
            }

            return self::errorResponse('Demasiadas solicitudes. Intenta nuevamente en unos minutos.', 429);
        });

        $exceptions->render(function (\InvalidArgumentException $e, Request $request) {
            if (! self::shouldRenderJson($request)) {
                return null;
            }

            return self::errorResponse($e->getMessage(), 400);
        });

        $exceptions->render(function (OutOfStockException $e, Request $request) {
            if (! self::shouldRenderJson($request)) {
                return null;
            }

            return self::errorResponse($e->getMessage(), 422);
        });

        $exceptions->render(function (PaymentFailedException $e, Request $request) {
            if (! self::shouldRenderJson($request)) {
                return null;
            }

            return self::errorResponse('Error en el procesamiento del pago.', 402, [
                'payment' => [$e->getMessage()],
            ]);
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! self::shouldRenderJson($request)) {
                return null;
            }

            return self::errorResponse($e->getMessage() ?: 'Error HTTP.', $e->getStatusCode());
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! self::shouldRenderJson($request)) {
                return null;
            }

            return self::errorResponse(
                config('app.debug') ? $e->getMessage() : 'Ocurrio un error interno en el servidor.',
                500
            );
        });
    }

    private static function shouldRenderJson(Request $request): bool
    {
        $forceJsonValue = getenv('FORCE_JSON_RESPONSE') ?: ($_ENV['FORCE_JSON_RESPONSE'] ?? false);
        $forceJson = filter_var($forceJsonValue, FILTER_VALIDATE_BOOLEAN);

        return $forceJson || $request->is('api/*') || $request->expectsJson();
    }

    private static function errorResponse(string $message, int $status, mixed $errors = null): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
