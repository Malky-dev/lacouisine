<?php

declare(strict_types=1);

namespace App\DTO\API\V1\Category;

final readonly class CategoryDetails
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public int $recipeCount,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {
    }
}
