<?php

declare(strict_types=1);

namespace App\Controller\API\V1;

use App\Entity\Category;
use App\DTO\API\V1\Category\CreateCategoryInput;
use App\DTO\API\V1\Category\UpdateCategoryInput;
use App\Mapper\API\V1\CategoryMapper;
use App\Mapper\API\V1\PaginationMapper;
use App\Repository\CategoryRepository;
use App\Service\CategoryManagementService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class CategoriesController extends AbstractController
{
    #[Route(
        '/api/v1/categories',
        name: 'api_v1_categories_create',
        methods: ['POST'],
        defaults: ['_format' => 'json'],
    )]
    #[IsGranted('ROLE_MANAGER')]
    public function create(
        #[MapRequestPayload] CreateCategoryInput $input,
        CategoryManagementService $service,
        CategoryMapper $mapper,
    ): JsonResponse {
        $category = $service->create($input->name);

        return $this->json(
            ['data' => $mapper->toDetails($category, 0)],
            Response::HTTP_CREATED,
            ['Location' => $this->generateUrl('api_v1_categories_show', [
                'slug' => $category->getSlug(),
            ])],
        );
    }

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

    #[Route(
        '/api/v1/categories/{id}',
        name: 'api_v1_categories_update',
        methods: ['PATCH'],
        requirements: ['id' => '[0-9]+'],
        defaults: ['_format' => 'json'],
    )]
    #[IsGranted('ROLE_MANAGER')]
    public function update(
        Category $category,
        #[MapRequestPayload] UpdateCategoryInput $input,
        CategoryManagementService $service,
        CategoryMapper $mapper,
    ): JsonResponse {
        $service->update($category, $input->name);

        return $this->json([
            'data' => $mapper->toDetails($category, $category->getRecipes()->count()),
        ]);
    }

    #[Route(
        '/api/v1/categories/{id}',
        name: 'api_v1_categories_delete',
        methods: ['DELETE'],
        requirements: ['id' => '[0-9]+'],
        defaults: ['_format' => 'json'],
    )]
    #[IsGranted('ROLE_MANAGER')]
    public function delete(
        Category $category,
        CategoryManagementService $service,
    ): Response {
        $service->delete($category);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
