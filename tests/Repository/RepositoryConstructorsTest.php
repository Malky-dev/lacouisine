<?php

namespace App\Tests\Repository;

use App\Entity\Category;
use App\Entity\Recipe;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\RecipeRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\PaginatorInterface;
use PHPUnit\Framework\TestCase;

final class RepositoryConstructorsTest extends TestCase
{
    public function testUserRepositoryConstructs(): void
    {
        $registry = $this->mockRegistryFor(User::class);
        $repo = new UserRepository($registry);
        $this->assertInstanceOf(UserRepository::class, $repo);
    }

    public function testRecipeRepositoryConstructs(): void
    {
        $registry = $this->mockRegistryFor(Recipe::class);
        $paginator = $this->createMock(PaginatorInterface::class);
        $repo = new RecipeRepository($registry, $paginator);
        $this->assertInstanceOf(RecipeRepository::class, $repo);
    }

    public function testCategoryRepositoryConstructs(): void
    {
        $registry = $this->mockRegistryFor(Category::class);
        $paginator = $this->createMock(PaginatorInterface::class);
        $repo = new CategoryRepository($registry, $paginator);
        $this->assertInstanceOf(CategoryRepository::class, $repo);
    }

    private function mockRegistryFor(string $class): ManagerRegistry
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getClassMetadata')->with($class)->willReturn(new ClassMetadata($class));

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->with($class)->willReturn($em);

        return $registry;
    }
}




