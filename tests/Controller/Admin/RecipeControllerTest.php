<?php

namespace App\Tests\Controller\Admin;

use App\Controller\Admin\RecipeController;
use App\Entity\Recipe;
use App\Form\RecipeType;
use App\Repository\RecipeRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class RecipeControllerTest extends TestCase
{
    public function testIndexRendersRecipes(): void
    {
        $controller = $this->getMockBuilder(RecipeController::class)
            ->onlyMethods(['render'])
            ->getMock();

        $repository = $this->createMock(RecipeRepository::class);
        $pagination = $this->createMock(\Knp\Component\Pager\Pagination\PaginationInterface::class);
        $request = Request::create('/admin/recettes/', 'GET', ['page' => 2]);

        $repository->expects($this->once())->method('paginateRecipes')->with(2)->willReturn($pagination);

        $controller->expects($this->once())
            ->method('render')
            ->with('admin/recipe/index.html.twig', ['recipes' => $pagination])
            ->willReturn(new Response('ok'));

        $response = $controller->index($repository, $request);
        $this->assertSame('ok', $response->getContent());
    }

    public function testCreatePersistsWhenValid(): void
    {
        $controller = $this->getMockBuilder(RecipeController::class)
            ->onlyMethods(['createForm', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $recipe = new Recipe();
        $form = $this->createMock(FormInterface::class);
        $form->expects($this->once())->method('handleRequest');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);

        $controller->expects($this->once())->method('createForm')->with(RecipeType::class, $recipe)->willReturn($form);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist')->with($recipe);
        $em->expects($this->once())->method('flush');

        $controller->expects($this->once())->method('addFlash')->with('success', 'La recette a bien été créée');
        $controller->expects($this->once())->method('redirectToRoute')->with('admin.recipe.index')
            ->willReturn(new RedirectResponse('/admin/recettes/'));

        $response = $controller->create(new Request(), $em);
        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testCreateRendersFormWhenInvalid(): void
    {
        $controller = $this->getMockBuilder(RecipeController::class)
            ->onlyMethods(['createForm', 'render'])
            ->getMock();

        $recipe = new Recipe();
        $form = $this->createMock(FormInterface::class);
        $form->expects($this->once())->method('handleRequest');
        $form->method('isSubmitted')->willReturn(false);
        $form->method('isValid')->willReturn(false);

        $controller->expects($this->once())->method('createForm')->with(RecipeType::class, $recipe)->willReturn($form);
        $controller->expects($this->once())->method('render')
            ->with('admin/recipe/create.html.twig', ['form' => $form])
            ->willReturn(new Response('form'));

        $response = $controller->create(new Request(), $this->createMock(EntityManagerInterface::class));
        $this->assertSame('form', $response->getContent());
    }

    public function testEditFlushesWhenValid(): void
    {
        $controller = $this->getMockBuilder(RecipeController::class)
            ->onlyMethods(['createForm', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $recipe = new Recipe();
        $recipe->setTitle('Demo');

        $form = $this->createMock(FormInterface::class);
        $form->expects($this->once())->method('handleRequest');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);

        $controller->expects($this->once())->method('createForm')->with(RecipeType::class, $recipe)->willReturn($form);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $controller->expects($this->once())->method('addFlash')
            ->with('success', 'La recette Demo a bien été modifiée');
        $controller->expects($this->once())->method('redirectToRoute')->with('admin.recipe.index')
            ->willReturn(new RedirectResponse('/admin/recettes/'));

        $response = $controller->edit($recipe, new Request(), $em);
        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testEditRendersWhenInvalid(): void
    {
        $controller = $this->getMockBuilder(RecipeController::class)
            ->onlyMethods(['createForm', 'render'])
            ->getMock();

        $recipe = new Recipe();

        $form = $this->createMock(FormInterface::class);
        $form->expects($this->once())->method('handleRequest');
        $form->method('isSubmitted')->willReturn(false);
        $form->method('isValid')->willReturn(false);

        $controller->expects($this->once())->method('createForm')->with(RecipeType::class, $recipe)->willReturn($form);
        $controller->expects($this->once())->method('render')
            ->with('admin/recipe/edit.html.twig', ['recipe' => $recipe, 'form' => $form])
            ->willReturn(new Response('edit'));

        $response = $controller->edit($recipe, new Request(), $this->createMock(EntityManagerInterface::class));
        $this->assertSame('edit', $response->getContent());
    }

    public function testDeleteRemovesAndRedirects(): void
    {
        $controller = $this->getMockBuilder(RecipeController::class)
            ->onlyMethods(['addFlash', 'redirectToRoute'])
            ->getMock();

        $em = $this->createMock(EntityManagerInterface::class);
        $recipe = new Recipe();

        $em->expects($this->once())->method('remove')->with($recipe);
        $em->expects($this->once())->method('flush');

        $controller->expects($this->once())->method('addFlash')->with('success', 'La recette a bien été supprimée');
        $controller->expects($this->once())->method('redirectToRoute')->with('admin.recipe.index')
            ->willReturn(new RedirectResponse('/admin/recettes/'));

        $response = $controller->delete($recipe, $em);
        $this->assertInstanceOf(RedirectResponse::class, $response);
    }
}

