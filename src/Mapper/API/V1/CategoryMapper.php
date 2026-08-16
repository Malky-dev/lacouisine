<?php

declare(strict_types=1);

namespace App\Mapper\API\V1;

use App\DTO\API\V1\Category\CategoryDetails;
use App\DTO\API\V1\Category\CategoryListItem;
use App\Entity\Category;

final class CategoryMapper
{
    public function toListItem(Category $category, int $recipeCount): CategoryListItem
    {
        return new CategoryListItem(
            id: (int) $category->getId(),
            name: $category->getName(),
            slug: $category->getSlug(),
            recipeCount: $recipeCount,
        );
    }

    public function toDetails(Category $category, int $recipeCount): CategoryDetails
    {
        return new CategoryDetails(
            id: (int) $category->getId(),
            name: $category->getName(),
            slug: $category->getSlug(),
            recipeCount: $recipeCount,
            createdAt: $category->getCreatedAt()?->format(DATE_ATOM),
            updatedAt: $category->getUpdatedAt()?->format(DATE_ATOM),
        );
    }
}
