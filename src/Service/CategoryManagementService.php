<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\String\Slugger\SluggerInterface;

final readonly class CategoryManagementService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CategoryRepository $repository,
        private SluggerInterface $slugger,
    ) {
    }

    public function create(string $name): Category
    {
        $normalizedName = trim($name);
        $slug = $this->createSlug($normalizedName);
        $this->assertUnique($normalizedName, $slug);

        $now = new DateTimeImmutable();
        $category = (new Category())
            ->setName($normalizedName)
            ->setSlug($slug)
            ->setCreatedAt($now)
            ->setUpdatedAt($now);

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $category;
    }

    public function update(Category $category, string $name): Category
    {
        $normalizedName = trim($name);
        $slug = $this->createSlug($normalizedName);
        $this->assertUnique($normalizedName, $slug, $category);

        $category
            ->setName($normalizedName)
            ->setSlug($slug)
            ->setUpdatedAt(new DateTimeImmutable());

        $this->entityManager->flush();

        return $category;
    }

    public function delete(Category $category): void
    {
        if (!$category->getRecipes()->isEmpty()) {
            throw new ConflictHttpException('A category used by recipes cannot be deleted.');
        }

        $this->entityManager->remove($category);
        $this->entityManager->flush();
    }

    private function createSlug(string $name): string
    {
        return $this->slugger->slug($name)->lower()->toString();
    }

    private function assertUnique(string $name, string $slug, ?Category $current = null): void
    {
        foreach (['name' => $name, 'slug' => $slug] as $field => $value) {
            $existing = $this->repository->findOneBy([$field => $value]);

            if ($existing !== null && $existing !== $current) {
                throw new ConflictHttpException('A category with this name already exists.');
            }
        }
    }
}
