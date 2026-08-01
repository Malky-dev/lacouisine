<?php

declare(strict_types=1);

namespace App\Controller\API\V1;

use App\DTO\API\V1\User\UpdateUserInput;
use App\Entity\User;
use App\Mapper\API\V1\PaginationMapper;
use App\Mapper\API\V1\UserMapper;
use App\Repository\UserRepository;
use App\Security\Voter\UserVoter;
use App\Service\UserDeletionService;
use App\Service\UserManagementService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/users', name: 'api_v1_users_')]
#[IsGranted('ROLE_ADMIN')]
final class UsersController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'], defaults: ['_format' => 'json'])]
    public function index(
        UserRepository $repository,
        Request $request,
        UserMapper $mapper,
        PaginationMapper $paginationMapper,
    ): JsonResponse {
        $users = $repository->paginateUsers($request->query->getInt('page', 1));

        return $this->json([
            'data' => array_map(
                static fn (User $user) => $mapper->toListItem($user),
                $users->getItems(),
            ),
            'meta' => $paginationMapper->toMeta($users),
        ]);
    }

    #[Route(
        '/{id}',
        name: 'show',
        methods: ['GET'],
        requirements: ['id' => Requirement::DIGITS],
        defaults: ['_format' => 'json'],
    )]
    public function show(User $user, UserMapper $mapper): JsonResponse
    {
        return $this->json(['data' => $mapper->toDetails($user)]);
    }

    #[Route(
        '/{id}',
        name: 'update',
        methods: ['PATCH'],
        requirements: ['id' => Requirement::DIGITS],
        defaults: ['_format' => 'json'],
    )]
    #[IsGranted(UserVoter::EDIT, subject: 'user')]
    public function update(
        User $user,
        #[MapRequestPayload] UpdateUserInput $input,
        UserManagementService $service,
        UserMapper $mapper,
    ): JsonResponse {
        $actor = $this->getUser();
        if (!$actor instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $service->update($user, $actor, $input);

        return $this->json(['data' => $mapper->toDetails($user)]);
    }

    #[Route(
        '/{id}',
        name: 'delete',
        methods: ['DELETE'],
        requirements: ['id' => Requirement::DIGITS],
        defaults: ['_format' => 'json'],
    )]
    #[IsGranted(UserVoter::DELETE, subject: 'user')]
    public function delete(User $user, UserDeletionService $service): Response
    {
        $service->delete($user);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
