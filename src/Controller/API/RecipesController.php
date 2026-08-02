<?php

declare(strict_types=1);

namespace App\Controller\API;

use App\DTO\API\V1\Recipe\CreateRecipeInput;
use App\DTO\API\V1\Recipe\UpdateRecipeInput;
use App\Entity\Recipe;
use App\Entity\User;
use App\Enum\RecipeVisibility;
use App\Mapper\API\V1\PaginationMapper;
use App\Mapper\API\V1\RecipeMapper;
use App\Repository\RecipeRepository;
use App\Security\Voter\RecipeVoter;
use App\Service\RecipeManagementService;
use App\Service\RecipeThumbnailService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RecipesController extends AbstractController
{
    #[Route(
        '/api/v1/me/recipes',
        name: 'api_v1_me_recipes_index',
        methods: ['GET'],
        defaults: ['_format' => 'json'],
    )]
    #[IsGranted('ROLE_CREATOR')]
    public function mine(
        Request $request,
        RecipeRepository $repository,
        RecipeMapper $mapper,
        PaginationMapper $paginationMapper,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $visibilityValue = trim($request->query->getString('visibility'));
        $visibility = $visibilityValue === ''
            ? null
            : RecipeVisibility::tryFrom($visibilityValue);
        if ($visibilityValue !== '' && $visibility === null) {
            throw new BadRequestHttpException('The visibility filter is invalid.');
        }

        $category = trim($request->query->getString('category')) ?: null;
        $search = trim($request->query->getString('q')) ?: null;
        if ($search !== null && mb_strlen($search) > 100) {
            throw new BadRequestHttpException('The search filter is too long.');
        }

        $recipes = $repository->paginateByCreator(
            creator: $user,
            page: $request->query->getInt('page', 1),
            visibility: $visibility,
            categorySlug: $category,
            search: $search,
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
        '/api/v1/recipes',
        name: 'api_v1_recipes_create',
        methods: ['POST'],
        defaults: ['_format' => 'json'],
    )]
    #[IsGranted('ROLE_CREATOR')]
    public function create(
        #[MapRequestPayload] CreateRecipeInput $input,
        RecipeManagementService $service,
        RecipeMapper $mapper,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $recipe = $service->create($input, $user);

        return $this->json(
            ['data' => $mapper->toDetails($recipe)],
            Response::HTTP_CREATED,
            ['Location' => $this->generateUrl('api_v1_recipes_show', [
                'id' => $recipe->getId(),
            ])],
        );
    }

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
        $recipes = $repository->paginatePublicRecipes(
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
        $recipes = $repository->paginatePublicRecipes(
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
    #[IsGranted(RecipeVoter::VIEW, subject: 'recipe')]
    public function show(
        Recipe $recipe,
        RecipeMapper $mapper,
    ): JsonResponse {
        return $this->json([
            'data' => $mapper->toDetails($recipe),
        ]);
    }

    #[Route(
        '/api/v1/recipes/{id}',
        name: 'api_v1_recipes_update',
        methods: ['PATCH'],
        requirements: ['id' => Requirement::DIGITS],
        defaults: ['_format' => 'json'],
    )]
    #[IsGranted(RecipeVoter::EDIT, subject: 'recipe')]
    public function update(
        Recipe $recipe,
        #[MapRequestPayload] UpdateRecipeInput $input,
        RecipeManagementService $service,
        RecipeMapper $mapper,
    ): JsonResponse {
        $service->update($recipe, $input);

        return $this->json(['data' => $mapper->toDetails($recipe)]);
    }

    #[Route(
        '/api/v1/recipes/{id}',
        name: 'api_v1_recipes_delete',
        methods: ['DELETE'],
        requirements: ['id' => Requirement::DIGITS],
        defaults: ['_format' => 'json'],
    )]
    #[IsGranted(RecipeVoter::DELETE, subject: 'recipe')]
    public function delete(
        Recipe $recipe,
        RecipeManagementService $service,
    ): Response {
        $service->delete($recipe);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route(
        '/api/v1/recipes/{id}/thumbnail',
        name: 'api_v1_recipes_thumbnail_upload',
        methods: ['POST'],
        requirements: ['id' => Requirement::DIGITS],
        defaults: ['_format' => 'json'],
    )]
    #[IsGranted(RecipeVoter::EDIT, subject: 'recipe')]
    public function uploadThumbnail(
        Recipe $recipe,
        Request $request,
        ValidatorInterface $validator,
        RecipeThumbnailService $service,
        RecipeMapper $mapper,
    ): JsonResponse {
        $file = $request->files->get('thumbnail');
        if ($file === null) {
            throw new BadRequestHttpException('The thumbnail file is required.');
        }

        $violations = $validator->validate($file, [
            new Assert\Image(
                maxSize: '5M',
                mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
            ),
        ]);
        if ($violations->count() > 0) {
            throw new ValidationFailedException($file, $violations);
        }

        $service->upload($recipe, $file);

        return $this->json(['data' => $mapper->toDetails($recipe)]);
    }

    #[Route(
        '/api/v1/recipes/{id}/thumbnail',
        name: 'api_v1_recipes_thumbnail_delete',
        methods: ['DELETE'],
        requirements: ['id' => Requirement::DIGITS],
        defaults: ['_format' => 'json'],
    )]
    #[IsGranted(RecipeVoter::EDIT, subject: 'recipe')]
    public function deleteThumbnail(
        Recipe $recipe,
        RecipeThumbnailService $service,
    ): Response {
        $service->delete($recipe);

        return new Response(status: Response::HTTP_NO_CONTENT);
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
    #[IsGranted(RecipeVoter::VIEW, subject: 'recipe')]
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
