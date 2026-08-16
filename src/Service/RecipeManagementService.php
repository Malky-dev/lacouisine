<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\API\V1\Recipe\CreateRecipeInput;
use App\DTO\API\V1\Recipe\UpdateRecipeInput;
use App\Entity\Category;
use App\Entity\Recipe;
use App\Entity\User;
use App\Enum\RecipeVisibility;
use App\Repository\CategoryRepository;
use App\Repository\RecipeRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\String\Slugger\SluggerInterface;

final readonly class RecipeManagementService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RecipeRepository $recipeRepository,
        private CategoryRepository $categoryRepository,
        private SluggerInterface $slugger,
    ) {
    }

    public function create(CreateRecipeInput $input, User $creator): Recipe
    {
        $title = trim($input->title);
        $slug = $this->createSlug($title);
        $this->assertUnique($title, $slug);
        $now = new DateTimeImmutable();

        $recipe = (new Recipe())
            ->setTitle($title)
            ->setSlug($slug)
            ->setContent($input->content)
            ->setDuration($input->duration)
            ->setCategory($this->findCategory($input->categoryId))
            ->setVisibility(RecipeVisibility::from($input->visibility))
            ->setCreatedBy($creator)
            ->setCreatedAt($now)
            ->setUpdatedAt($now);

        $this->entityManager->persist($recipe);
        $this->entityManager->flush();

        return $recipe;
    }

    public function update(Recipe $recipe, UpdateRecipeInput $input): Recipe
    {
        if ($input->title !== null) {
            $title = trim($input->title);
            $slug = $this->createSlug($title);
            $this->assertUnique($title, $slug, $recipe);
            $recipe->setTitle($title)->setSlug($slug);
        }

        if ($input->content !== null) {
            $recipe->setContent($input->content);
        }
        if ($input->duration !== null) {
            $recipe->setDuration($input->duration);
        }
        if ($input->categoryId !== null) {
            $recipe->setCategory($this->findCategory($input->categoryId));
        }
        if ($input->visibility !== null) {
            $recipe->setVisibility(RecipeVisibility::from($input->visibility));
        }

        $recipe->setUpdatedAt(new DateTimeImmutable());
        $this->entityManager->flush();

        return $recipe;
    }

    public function delete(Recipe $recipe): void
    {
        $this->entityManager->remove($recipe);
        $this->entityManager->flush();
    }

    private function findCategory(int $id): Category
    {
        return $this->categoryRepository->find($id)
            ?? throw new NotFoundHttpException('The requested category was not found.');
    }

    private function createSlug(string $title): string
    {
        return $this->slugger->slug($title)->lower()->toString();
    }

    private function assertUnique(string $title, string $slug, ?Recipe $current = null): void
    {
        foreach (['title' => $title, 'slug' => $slug] as $field => $value) {
            $existing = $this->recipeRepository->findOneBy([$field => $value]);
            if ($existing !== null && $existing !== $current) {
                throw new ConflictHttpException('A recipe with this title already exists.');
            }
        }
    }
}
