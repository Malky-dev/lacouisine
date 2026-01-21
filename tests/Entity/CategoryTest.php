<?php

namespace App\Tests\Entity;

use App\Entity\Category;
use App\Entity\Recipe;
use PHPUnit\Framework\TestCase;

final class CategoryTest extends TestCase
{
    public function testGettersSettersAndRecipesRelation(): void
    {
        $category = new Category();

        $this->assertNull($category->getId());
        $this->assertSame('', $category->getName());
        $this->assertSame('', $category->getSlug());
        $this->assertNull($category->getCreatedAt());
        $this->assertNull($category->getUpdatedAt());
        $this->assertCount(0, $category->getRecipes());

        $now = new \DateTimeImmutable();
        $category
            ->setName('Desserts')
            ->setSlug('desserts')
            ->setCreatedAt($now)
            ->setUpdatedAt($now);

        $this->assertSame('Desserts', $category->getName());
        $this->assertSame('desserts', $category->getSlug());
        $this->assertSame($now, $category->getCreatedAt());
        $this->assertSame($now, $category->getUpdatedAt());

        $recipe = new Recipe();
        $this->assertNull($recipe->getCategory());

        $category->addRecipe($recipe);
        $this->assertCount(1, $category->getRecipes());
        $this->assertSame($category, $recipe->getCategory());

        // idempotent add
        $category->addRecipe($recipe);
        $this->assertCount(1, $category->getRecipes());

        $category->removeRecipe($recipe);
        $this->assertCount(0, $category->getRecipes());
        $this->assertNull($recipe->getCategory());
    }
}




