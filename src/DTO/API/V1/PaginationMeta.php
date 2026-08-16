<?php

declare(strict_types=1);

namespace App\DTO\API\V1;

final readonly class PaginationMeta
{
    public function __construct(
        public int $page,
        public int $perPage,
        public int $total,
        public int $lastPage,
    ) {
    }
}
