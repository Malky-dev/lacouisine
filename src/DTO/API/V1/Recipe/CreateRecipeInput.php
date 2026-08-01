<?php

declare(strict_types=1);

namespace App\DTO\API\V1\Recipe;

use App\Validator\BanWord;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateRecipeInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 5, max: 255)]
        #[BanWord]
        public string $title = '',
        #[Assert\NotBlank]
        public string $content = '',
        #[Assert\Positive]
        #[Assert\LessThan(1440)]
        public ?int $duration = null,
        #[Assert\Positive]
        public int $categoryId = 0,
        #[Assert\Choice(['public', 'private'])]
        public string $visibility = 'public',
    ) {
    }
}
