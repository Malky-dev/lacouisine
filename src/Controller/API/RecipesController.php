<?php 

namespace App\Controller\API;

use App\Entity\Recipe;
use App\Repository\RecipeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Serializer\SerializerInterface;

class RecipesController extends AbstractController
{

    #[Route(
        '/api/v1/recipes',
        name: 'api_v1_recipes_index',
        methods: ['GET'],
        defaults: ['_format' => 'json'],
    )]
    #[Route(
        '/api/recipes.{_format}',
        name: 'api_legacy_recipes_index',
        methods: ['GET'],
        requirements: ['_format' => 'json|xml|csv'],
        defaults: ['_format' => 'json'],
    )]
    public function index(
        RecipeRepository $repository,
        Request $request,
        SerializerInterface $serializer,
    ): Response {
        $recipes = $repository->paginateRecipes(
            $request->query->getInt('page', 1),
        );

        $format = $request->getRequestFormat();

        $data = $serializer->serialize($recipes, $format, [
            'groups' => ['recipes.index'],
        ]);

        return new Response(
            $data,
            Response::HTTP_OK,
            ['Content-Type' => $this->getContentType($format)],
        );
    }

    #[Route(
        '/api/v1/recipes/{id}',
        name: 'api_v1_recipes_show',
        methods: ['GET'],
        requirements: ['id' => Requirement::DIGITS],
        defaults: ['_format' => 'json'],
    )]
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
    public function show(
        Recipe $recipe,
        Request $request,
        SerializerInterface $serializer,
    ): Response {
        $format = $request->getRequestFormat();

        $data = $serializer->serialize($recipe, $format, [
            'groups' => ['recipes.index', 'recipes.show'],
        ]);

        return new Response(
            $data,
            Response::HTTP_OK,
            ['Content-Type' => $this->getContentType($format)],
        );
    }

    private function getContentType(string $format): String
    {

        return match ($format) {
            'xml' => 'application/xml',
            'csv' => 'text/csv',
            default => 'application/json',
        };
        
    }

}
