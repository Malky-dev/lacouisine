<?php

declare(strict_types=1);

namespace App\EventSubscriber\API\V1;

use App\Http\API\V1\ApiErrorResponseFactory;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTExpiredEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTInvalidEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTNotFoundEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ApiErrorResponseFactory $responseFactory,
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', -100],
            Events::AUTHENTICATION_FAILURE => 'onAuthenticationFailure',
            Events::JWT_NOT_FOUND => 'onJwtNotFound',
            Events::JWT_INVALID => 'onJwtInvalid',
            Events::JWT_EXPIRED => 'onJwtExpired',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$this->isV1Request($event->getRequest())) {
            return;
        }

        $exception = $event->getThrowable();
        $statusCode = $exception instanceof HttpExceptionInterface
            ? $exception->getStatusCode()
            : Response::HTTP_INTERNAL_SERVER_ERROR;
        $headers = $exception instanceof HttpExceptionInterface
            ? $exception->getHeaders()
            : [];

        if ($statusCode >= Response::HTTP_INTERNAL_SERVER_ERROR) {
            $this->logger->error('Unhandled API exception.', [
                'exception' => $exception,
            ]);
        }

        $event->setResponse(
            $this->responseFactory->createForStatus($statusCode, $headers),
        );
    }

    public function onAuthenticationFailure(AuthenticationFailureEvent $event): void
    {
        if (!$this->isV1Request($event->getRequest())) {
            return;
        }

        $event->setResponse($this->responseFactory->create(
            statusCode: Response::HTTP_UNAUTHORIZED,
            code: 'INVALID_CREDENTIALS',
            message: 'Invalid credentials.',
        ));
    }

    public function onJwtNotFound(JWTNotFoundEvent $event): void
    {
        if (!$this->isV1Request($event->getRequest())) {
            return;
        }

        $event->setResponse($this->responseFactory->createForStatus(
            Response::HTTP_UNAUTHORIZED,
        ));
    }

    public function onJwtInvalid(JWTInvalidEvent $event): void
    {
        if (!$this->isV1Request($event->getRequest())) {
            return;
        }

        $event->setResponse($this->responseFactory->create(
            statusCode: Response::HTTP_UNAUTHORIZED,
            code: 'INVALID_TOKEN',
            message: 'The authentication token is invalid.',
        ));
    }

    public function onJwtExpired(JWTExpiredEvent $event): void
    {
        if (!$this->isV1Request($event->getRequest())) {
            return;
        }

        $event->setResponse($this->responseFactory->create(
            statusCode: Response::HTTP_UNAUTHORIZED,
            code: 'TOKEN_EXPIRED',
            message: 'The authentication token has expired.',
        ));
    }

    private function isV1Request(?Request $request): bool
    {
        if ($request === null) {
            return false;
        }

        $path = $request->getPathInfo();

        return $path === '/api/v1' || str_starts_with($path, '/api/v1/');
    }
}
