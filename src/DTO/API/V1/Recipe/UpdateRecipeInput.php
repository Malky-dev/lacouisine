<?php

declare(strict_types=1);

namespace App\DTO\API\V1\Recipe;

use App\Validator\BanWord;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateRecipeInput
{
    public function __construct(
        #[Assert\Length(min: 5, max: 255)]
        #[BanWord]
        public ?string $title = null,
        #[Assert\NotBlank(allowNull: true)]
        public ?string $content = null,
        #[Assert\Positive]
        #[Assert\LessThan(1440)]
        public ?int $duration = null,
        #[Assert\Positive]
        public ?int $categoryId = null,
        #[Assert\Choice(['public', 'private'])]
        public ?string $visibility = null,
    ) {
    }
}
