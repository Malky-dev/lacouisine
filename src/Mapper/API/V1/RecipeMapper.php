<?php

declare(strict_types=1);

namespace App\Mapper\API\V1;

use App\DTO\API\V1\Recipe\CategorySummary;
use App\DTO\API\V1\Recipe\RecipeDetails;
use App\DTO\API\V1\Recipe\RecipeListItem;
use App\Entity\Category;
use App\Entity\Recipe;
use Vich\UploaderBundle\Storage\StorageInterface;

final class RecipeMapper
{
    public function __construct(private readonly StorageInterface $storage)
    {
    }

    public function toListItem(Recipe $recipe): RecipeListItem
    {
        return new RecipeListItem(
            id: (int) $recipe->getId(),
            title: $recipe->getTitle(),
            slug: $recipe->getSlug(),
            duration: $recipe->getDuration(),
            visibility: $recipe->getVisibility()->value,
        );
    }

    public function toDetails(Recipe $recipe): RecipeDetails
    {
        return new RecipeDetails(
            id: (int) $recipe->getId(),
            title: $recipe->getTitle(),
            slug: $recipe->getSlug(),
            content: $recipe->getContent(),
            duration: $recipe->getDuration(),
            visibility: $recipe->getVisibility()->value,
            thumbnailUrl: $this->storage->resolveUri($recipe, 'thumbnailFile'),
            category: $this->toCategorySummary($recipe->getCategory()),
            createdAt: $recipe->getCreatedAt()?->format(DATE_ATOM),
            updatedAt: $recipe->getUpdatedAt()?->format(DATE_ATOM),
        );
    }

    private function toCategorySummary(?Category $category): ?CategorySummary
    {
        if ($category === null) {
            return null;
        }

        return new CategorySummary(
            id: (int) $category->getId(),
            name: $category->getName(),
            slug: $category->getSlug(),
        );
    }
}
