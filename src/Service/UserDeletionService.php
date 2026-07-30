<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\RecipeRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class UserDeletionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RecipeRepository $recipeRepository,
    ) {
    }

    public function delete(User $user): void
    {
        $this->entityManager->wrapInTransaction(function () use ($user): void {
            $this->recipeRepository->privatizeAndDetachByCreator($user);
            $this->entityManager->remove($user);
            $this->entityManager->flush();
        });
    }
}
