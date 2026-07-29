<?php

declare(strict_types=1);

namespace App\DTO\API\V1\Recipe;

final readonly class RecipeDetails
{
    public function __construct(
        public int $id,
        public string $title,
        public string $slug,
        public string $content,
        public ?int $duration,
        public ?string $thumbnail,
        public ?CategorySummary $category,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {
    }
}
