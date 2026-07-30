<?php

declare(strict_types=1);

namespace App\Mapper\API\V1;

use App\DTO\API\V1\PaginationMeta;
use Knp\Component\Pager\Pagination\PaginationInterface;

final class PaginationMapper
{
    public function toMeta(PaginationInterface $pagination): PaginationMeta
    {
        $perPage = max(1, $pagination->getItemNumberPerPage());

        return new PaginationMeta(
            page: $pagination->getCurrentPageNumber(),
            perPage: $perPage,
            total: $pagination->getTotalItemCount(),
            lastPage: (int) ceil($pagination->getTotalItemCount() / $perPage),
        );
    }
}
