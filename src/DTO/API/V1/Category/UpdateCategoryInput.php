<?php

declare(strict_types=1);

namespace App\DTO\API\V1\Category;

use App\Validator\BanWord;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateCategoryInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 5, max: 255)]
        #[BanWord]
        public string $name = '',
    ) {
    }
}
