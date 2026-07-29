<?php

declare(strict_types=1);

namespace App\Http\API\V1;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class ApiErrorResponseFactory
{
    /**
     * @param array<string, string> $headers
     */
    public function create(
        int $statusCode,
        string $code,
        string $message,
        array $headers = [],
    ): JsonResponse {
        return new JsonResponse(
            data: [
                'error' => [
                    'code' => $code,
                    'message' => $message,
                ],
            ],
            status: $statusCode,
            headers: $headers,
        );
    }

    /**
     * @param array<string, string> $headers
     */
    public function createForStatus(int $statusCode, array $headers = []): JsonResponse
    {
        [$code, $message] = match ($statusCode) {
            Response::HTTP_BAD_REQUEST => [
                'BAD_REQUEST',
                'The request is invalid.',
            ],
            Response::HTTP_UNAUTHORIZED => [
                'UNAUTHORIZED',
                'Authentication is required.',
            ],
            Response::HTTP_FORBIDDEN => [
                'FORBIDDEN',
                'Access to this resource is forbidden.',
            ],
            Response::HTTP_NOT_FOUND => [
                'RESOURCE_NOT_FOUND',
                'The requested resource was not found.',
            ],
            Response::HTTP_METHOD_NOT_ALLOWED => [
                'METHOD_NOT_ALLOWED',
                'The HTTP method is not allowed for this resource.',
            ],
            Response::HTTP_CONFLICT => [
                'CONFLICT',
                'The request conflicts with the current resource state.',
            ],
            Response::HTTP_UNPROCESSABLE_ENTITY => [
                'VALIDATION_ERROR',
                'The submitted data is invalid.',
            ],
            default => [
                'INTERNAL_SERVER_ERROR',
                'An unexpected error occurred.',
            ],
        };

        return $this->create($statusCode, $code, $message, $headers);
    }
}
