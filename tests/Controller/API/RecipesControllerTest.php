<?php

namespace App\Tests\Controller\API;

use App\Controller\API\RecipesController;
use App\Entity\Recipe;
use App\Repository\RecipeRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\Pagination\PaginationInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\String\UnicodeString;

final class RecipesControllerTest extends TestCase
{
    private RecipesController $controller;
    private RecipeRepository $repository;
    private SerializerInterface $serializer;
    private EntityManagerInterface $entityManager;
    private SluggerInterface $slugger;

    protected function setUp(): void
    {
        $this->controller = new RecipesController();
        $this->repository = $this->createMock(RecipeRepository::class);
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->slugger = $this->createMock(SluggerInterface::class);
    }

    public function testIndexReturnsJsonResponseByDefault(): void
    {
        $request = Request::create('/api/recipes.json');
        $request->setRequestFormat('json');
        $pagination = $this->createMock(PaginationInterface::class);

        $this->repository
            ->expects($this->once())
            ->method('paginateRecipes')
            ->with(1)
            ->willReturn($pagination);

        $this->serializer
            ->expects($this->once())
            ->method('serialize')
            ->with($pagination, 'json', ['groups' => ['recipes.index']])
            ->willReturn('{"data":[]}');

        $response = $this->controller->index($this->repository, $request, $this->serializer);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
        $this->assertSame('{"data":[]}', $response->getContent());
    }

    public function testIndexReturnsXmlResponse(): void
    {
        $request = Request::create('/api/recipes.xml');
        $request->setRequestFormat('xml');
        $pagination = $this->createMock(PaginationInterface::class);

        $this->repository
            ->expects($this->once())
            ->method('paginateRecipes')
            ->with(1)
            ->willReturn($pagination);

        $this->serializer
            ->expects($this->once())
            ->method('serialize')
            ->with($pagination, 'xml', ['groups' => ['recipes.index']])
            ->willReturn('<?xml version="1.0"?><data></data>');

        $response = $this->controller->index($this->repository, $request, $this->serializer);

        $this->assertSame('application/xml', $response->headers->get('Content-Type'));
        $this->assertSame('<?xml version="1.0"?><data></data>', $response->getContent());
    }

    public function testIndexReturnsCsvResponse(): void
    {
        $request = Request::create('/api/recipes.csv');
        $request->setRequestFormat('csv');
        $pagination = $this->createMock(PaginationInterface::class);

        $this->repository
            ->expects($this->once())
            ->method('paginateRecipes')
            ->with(1)
            ->willReturn($pagination);

        $this->serializer
            ->expects($this->once())
            ->method('serialize')
            ->with($pagination, 'csv', ['groups' => ['recipes.index']])
            ->willReturn("id,title\n1,Recipe 1");

        $response = $this->controller->index($this->repository, $request, $this->serializer);

        $this->assertSame('text/csv', $response->headers->get('Content-Type'));
        $this->assertSame("id,title\n1,Recipe 1", $response->getContent());
    }

    public function testIndexUsesPageParameter(): void
    {
        $request = Request::create('/api/recipes.json', 'GET', ['page' => 3]);
        $request->setRequestFormat('json');
        $pagination = $this->createMock(PaginationInterface::class);

        $this->repository
            ->expects($this->once())
            ->method('paginateRecipes')
            ->with(3)
            ->willReturn($pagination);

        $this->serializer->method('serialize')->willReturn('[]');

        $this->controller->index($this->repository, $request, $this->serializer);
        $this->assertTrue(true);
    }

    public function testShowReturnsJsonResponse(): void
    {
        $recipe = $this->createRecipe(1, 'Test', 'test');
        $request = Request::create('/api/recipes/1.json');
        $request->setRequestFormat('json');

        $this->serializer
            ->expects($this->once())
            ->method('serialize')
            ->with($recipe, 'json', ['groups' => ['recipes.index', 'recipes.show']])
            ->willReturn('{"id":1}');

        $response = $this->controller->show($recipe, $request, $this->serializer);

        $this->assertSame('application/json', $response->headers->get('Content-Type'));
        $this->assertSame('{"id":1}', $response->getContent());
    }

    public function testShowReturnsXmlResponse(): void
    {
        $recipe = $this->createRecipe(1, 'Test', 'test');
        $request = Request::create('/api/recipes/1.xml');
        $request->setRequestFormat('xml');

        $this->serializer
            ->expects($this->once())
            ->method('serialize')
            ->with($recipe, 'xml', ['groups' => ['recipes.index', 'recipes.show']])
            ->willReturn('<recipe/>');

        $response = $this->controller->show($recipe, $request, $this->serializer);

        $this->assertSame('application/xml', $response->headers->get('Content-Type'));
    }

    public function testShowReturnsCsvResponse(): void
    {
        $recipe = $this->createRecipe(1, 'Test', 'test');
        $request = Request::create('/api/recipes/1.csv');
        $request->setRequestFormat('csv');

        $this->serializer
            ->expects($this->once())
            ->method('serialize')
            ->with($recipe, 'csv', ['groups' => ['recipes.index', 'recipes.show']])
            ->willReturn("id,title\n1,Test");

        $response = $this->controller->show($recipe, $request, $this->serializer);

        $this->assertSame('text/csv', $response->headers->get('Content-Type'));
    }

    public function testCreatePersistsRecipeAndReturnsCreated(): void
    {
        $recipe = $this->createRecipe(null, 'New', null);
        $recipe->setContent('c');
        $request = Request::create('/api/recipes', 'POST');

        $slugString = $this->createMock(UnicodeString::class);
        $slugString->method('lower')->willReturnSelf();
        $slugString->method('toString')->willReturn('new');

        $this->slugger->expects($this->once())->method('slug')->with('New')->willReturn($slugString);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (Recipe $r) {
                return $r->getSlug() === 'new'
                    && $r->getCreatedAt() instanceof DateTimeImmutable
                    && $r->getUpdatedAt() instanceof DateTimeImmutable;
            }));
        $this->entityManager->expects($this->once())->method('flush');

        // set id for Location header
        $ref = new \ReflectionProperty($recipe, 'id');
        $ref->setAccessible(true);
        $ref->setValue($recipe, 1);

        // container for json()
        $container = $this->createMock(\Psr\Container\ContainerInterface::class);
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('serialize')->willReturn('{"id":1}');
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($serializer);
        $controllerRef = new \ReflectionClass($this->controller);
        $containerProp = $controllerRef->getProperty('container');
        $containerProp->setAccessible(true);
        $containerProp->setValue($this->controller, $container);

        $response = $this->controller->create($request, $this->slugger, $this->entityManager, $recipe);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $this->assertSame('/api/recipes/1', $response->headers->get('Location'));
    }

    public function testGetContentType(): void
    {
        $ref = new \ReflectionClass($this->controller);
        $method = $ref->getMethod('getContentType');
        $method->setAccessible(true);

        $this->assertSame('application/json', $method->invoke($this->controller, 'json'));
        $this->assertSame('application/xml', $method->invoke($this->controller, 'xml'));
        $this->assertSame('text/csv', $method->invoke($this->controller, 'csv'));
        $this->assertSame('application/json', $method->invoke($this->controller, 'unknown'));
    }

    private function createRecipe(?int $id, string $title, ?string $slug): Recipe
    {
        $recipe = new Recipe();
        if ($id !== null) {
            $ref = new \ReflectionProperty($recipe, 'id');
            $ref->setAccessible(true);
            $ref->setValue($recipe, $id);
        }
        $recipe->setTitle($title);
        if ($slug) {
            $recipe->setSlug($slug);
        }
        return $recipe;
    }
}



