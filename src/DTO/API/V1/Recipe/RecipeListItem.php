<?php

declare(strict_types=1);

namespace App\DTO\API\V1\Recipe;

final readonly class RecipeListItem
{
    public function __construct(
        public int $id,
        public string $title,
        public string $slug,
        public ?int $duration,
        public string $visibility,
    ) {
    }
}
