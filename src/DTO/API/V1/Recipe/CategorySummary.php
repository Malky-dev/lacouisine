<?php

declare(strict_types=1);

namespace App\DTO\API\V1\Recipe;

final readonly class CategorySummary
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
    ) {
    }
}
