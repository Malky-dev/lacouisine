<?php

declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\Recipe;
use App\Mapper\API\V1\PaginationMapper;
use App\Mapper\API\V1\RecipeMapper;
use App\Repository\RecipeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Serializer\SerializerInterface;

final class RecipesController extends AbstractController
{
    #[Route(
        '/api/v1/recipes',
        name: 'api_v1_recipes_index',
        methods: ['GET'],
        defaults: ['_format' => 'json'],
    )]
    public function index(
        RecipeRepository $repository,
        Request $request,
        RecipeMapper $mapper,
        PaginationMapper $paginationMapper,
    ): JsonResponse {
        $recipes = $repository->paginateRecipes(
            $request->query->getInt('page', 1),
        );

        return $this->json([
            'data' => array_map(
                static fn (Recipe $recipe) => $mapper->toListItem($recipe),
                $recipes->getItems(),
            ),
            'meta' => $paginationMapper->toMeta($recipes),
        ]);
    }

    #[Route(
        '/api/recipes.{_format}',
        name: 'api_legacy_recipes_index',
        methods: ['GET'],
        requirements: ['_format' => 'json|xml|csv'],
        defaults: ['_format' => 'json'],
    )]
    public function legacyIndex(
        RecipeRepository $repository,
        Request $request,
        SerializerInterface $serializer,
    ): Response {
        $recipes = $repository->paginateRecipes(
            $request->query->getInt('page', 1),
        );

        return $this->createLegacyResponse(
            data: $recipes,
            request: $request,
            serializer: $serializer,
            groups: ['recipes.index'],
        );
    }

    #[Route(
        '/api/v1/recipes/{id}',
        name: 'api_v1_recipes_show',
        methods: ['GET'],
        requirements: ['id' => Requirement::DIGITS],
        defaults: ['_format' => 'json'],
    )]
    public function show(
        Recipe $recipe,
        RecipeMapper $mapper,
    ): JsonResponse {
        return $this->json([
            'data' => $mapper->toDetails($recipe),
        ]);
    }

    #[Route(
        '/api/recipes/{id}.{_format}',
        name: 'api_legacy_recipes_show',
        methods: ['GET'],
        requirements: [
            'id' => Requirement::DIGITS,
            '_format' => 'json|xml|csv',
        ],
        defaults: ['_format' => 'json'],
    )]
    public function legacyShow(
        Recipe $recipe,
        Request $request,
        SerializerInterface $serializer,
    ): Response {
        return $this->createLegacyResponse(
            data: $recipe,
            request: $request,
            serializer: $serializer,
            groups: ['recipes.index', 'recipes.show'],
        );
    }

    /**
     * @param list<string> $groups
     */
    private function createLegacyResponse(
        mixed $data,
        Request $request,
        SerializerInterface $serializer,
        array $groups,
    ): Response {
        $format = $request->getRequestFormat();

        $content = $serializer->serialize($data, $format, [
            'groups' => $groups,
        ]);

        return new Response(
            $content,
            Response::HTTP_OK,
            ['Content-Type' => $this->getContentType($format)],
        );
    }

    private function getContentType(string $format): string
    {
        return match ($format) {
            'xml' => 'application/xml',
            'csv' => 'text/csv',
            default => 'application/json',
        };
    }
}
