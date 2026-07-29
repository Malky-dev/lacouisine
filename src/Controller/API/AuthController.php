<?php

declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\User;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AuthController extends AbstractController
{
    #[Route('/api/v1/auth/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(): never
    {
        throw new LogicException('This endpoint is intercepted by the API firewall.');
    }

    #[Route('/api/v1/me', name: 'api_auth_me', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $this->json([
            'data' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'verified' => $user->isVerified(),
            ],
        ]);
    }
}
