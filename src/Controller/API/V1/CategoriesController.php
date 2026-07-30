<?php

declare(strict_types=1);

namespace App\Controller\API\V1;

use App\Entity\Category;
use App\Mapper\API\V1\CategoryMapper;
use App\Mapper\API\V1\PaginationMapper;
use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class CategoriesController extends AbstractController
{
    #[Route(
        '/api/v1/categories',
        name: 'api_v1_categories_index',
        methods: ['GET'],
        defaults: ['_format' => 'json'],
    )]
    public function index(
        CategoryRepository $repository,
        Request $request,
        CategoryMapper $mapper,
        PaginationMapper $paginationMapper,
    ): JsonResponse {
        $categories = $repository->paginateCategoriesForApi(
            max(1, $request->query->getInt('page', 1)),
        );

        return $this->json([
            'data' => array_map(
                static fn (array $row) => $mapper->toListItem(
                    $row[0],
                    (int) $row['recipeCount'],
                ),
                $categories->getItems(),
            ),
            'meta' => $paginationMapper->toMeta($categories),
        ]);
    }

    #[Route(
        '/api/v1/categories/{slug}',
        name: 'api_v1_categories_show',
        methods: ['GET'],
        requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'],
        defaults: ['_format' => 'json'],
    )]
    public function show(
        string $slug,
        CategoryRepository $repository,
        CategoryMapper $mapper,
    ): JsonResponse {
        $result = $repository->findOneWithRecipeCountBySlug($slug);

        if ($result === null) {
            throw $this->createNotFoundException();
        }

        /** @var Category $category */
        $category = $result[0];

        return $this->json([
            'data' => $mapper->toDetails(
                $category,
                (int) $result['recipeCount'],
            ),
        ]);
    }
}
