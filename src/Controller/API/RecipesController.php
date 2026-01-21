<?php 

namespace App\Controller\API;

use App\Entity\Recipe;
use App\Repository\RecipeRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class RecipesController extends AbstractController
{

    #[Route(
        "/api/recipes.{_format}",
        requirements: ['_format' => 'json|xml|csv'],
        defaults: ['_format' => 'json'],
        methods: 'GET'
    )]
    public function index(RecipeRepository $repository, Request $request, SerializerInterface $serializer): Response
    {

        $recipes = $repository->paginateRecipes($request->query->getInt('page', 1));

        $format = $request->getRequestFormat();

        $data = $serializer->serialize($recipes, $format, [
                'groups' => ['recipes.index']
        ]);

        $contentType = $this->getContentType($format);

        return new Response($data, 200, ['Content-Type' => $contentType]);

    }

    #[Route(
        "/api/recipes",
        methods: 'POST'
    )]
    public function create(
        Request $request,
        SluggerInterface $slugger,
        EntityManagerInterface $em,
        #[MapRequestPayload(
            serializationContext: [
                'groups' => ['recipes.create']
            ]
        )]Recipe $recipe
    )
    {

        $recipe->setSlug($slugger->slug($recipe->getTitle())->lower()->toString());

        $recipe->setCreatedAt(new DateTimeImmutable);
        $recipe->setUpdatedAt(new DateTimeImmutable);

        $em->persist($recipe);
        $em->flush();

        return $this->json(
            $recipe,
            Response::HTTP_CREATED,
            ['Location' => sprintf('/api/recipes/%d', $recipe->getId())],
            ['groups' => ['recipes.index', 'recipes.show']]
        );

    }

    #[Route(
        "/api/recipes/{id}.{_format}",
        requirements: ['id' => Requirement::DIGITS, '_format' => 'json|xml|csv'],
        defaults: ['_format' => 'json']
    )]
    public function show(Recipe $recipe, Request $request, SerializerInterface $serializer): Response
    {

        $format = $request->getRequestFormat();

        $data = $serializer->serialize($recipe, $format, [
                'groups' => ['recipes.index', 'recipes.show']
        ]);

        $contentType = $this->getContentType($format);

        return new Response($data, 200, ['Content-Type' => $contentType]);

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