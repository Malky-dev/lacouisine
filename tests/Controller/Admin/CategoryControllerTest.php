<?php

namespace App\Tests\Controller\Admin;

use App\Controller\Admin\CategoryController;
use App\Entity\Category;
use App\Form\CategoryType;
use App\Repository\CategoryRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class CategoryControllerTest extends TestCase
{
    public function testIndexRendersCategories(): void
    {
        $controller = $this->getMockBuilder(CategoryController::class)
            ->onlyMethods(['render'])
            ->getMock();

        $repository = $this->createMock(CategoryRepository::class);
        $request = Request::create('/admin/category/', 'GET', ['page' => 2]);

        $pagination = $this->createMock(\Knp\Component\Pager\Pagination\PaginationInterface::class);

        $repository->expects($this->once())
            ->method('paginateCategory')
            ->with(2)
            ->willReturn($pagination);

        $controller->expects($this->once())
            ->method('render')
            ->with('admin/Category/index.html.twig', ['categories' => $pagination])
            ->willReturn(new Response('ok'));

        $response = $controller->index($repository, $request);

        $this->assertSame('ok', $response->getContent());
    }

    public function testCreatePersistsAndRedirectsOnValidForm(): void
    {
        $controller = $this->getMockBuilder(CategoryController::class)
            ->onlyMethods(['createForm', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $form = $this->createMock(FormInterface::class);
        $form->expects($this->once())->method('handleRequest')->with($this->isInstanceOf(Request::class));
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);

        $controller->expects($this->once())
            ->method('createForm')
            ->with(CategoryType::class, $this->isInstanceOf(Category::class))
            ->willReturn($form);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist')->with($this->isInstanceOf(Category::class));
        $em->expects($this->once())->method('flush');

        $controller->expects($this->once())->method('addFlash')->with('success', 'La Categorie a bien été créée');
        $controller->expects($this->once())->method('redirectToRoute')->with('admin.category.index')
            ->willReturn(new RedirectResponse('/admin/category/'));

        $response = $controller->create(new Request(), $em);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testCreateRendersFormWhenInvalid(): void
    {
        $controller = $this->getMockBuilder(CategoryController::class)
            ->onlyMethods(['createForm', 'render'])
            ->getMock();

        $form = $this->createMock(FormInterface::class);
        $form->expects($this->once())->method('handleRequest');
        $form->method('isSubmitted')->willReturn(false);
        $form->method('isValid')->willReturn(false);

        $controller->expects($this->once())
            ->method('createForm')
            ->willReturn($form);

        $controller->expects($this->once())
            ->method('render')
            ->with('admin/category/create.html.twig', ['form' => $form])
            ->willReturn(new Response('form'));

        $response = $controller->create(new Request(), $this->createMock(EntityManagerInterface::class));
        $this->assertSame('form', $response->getContent());
    }

    public function testEditUpdatesAndRedirectsOnValidForm(): void
    {
        $controller = $this->getMockBuilder(CategoryController::class)
            ->onlyMethods(['createForm', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $category = new Category();
        $category->setName('Demo')->setSlug('demo');

        $form = $this->createMock(FormInterface::class);
        $form->expects($this->once())->method('handleRequest');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);

        $controller->expects($this->once())->method('createForm')->with(CategoryType::class, $category)->willReturn($form);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $controller->expects($this->once())->method('addFlash')->with('success', 'La Categorie a bien été modifiée');
        $controller->expects($this->once())->method('redirectToRoute')->with('admin.category.index')
            ->willReturn(new RedirectResponse('/admin/category/'));

        $response = $controller->edit($category, new Request(), $em);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertInstanceOf(DateTimeImmutable::class, $category->getUpdatedAt());
    }

    public function testEditRendersFormWhenInvalid(): void
    {
        $controller = $this->getMockBuilder(CategoryController::class)
            ->onlyMethods(['createForm', 'render'])
            ->getMock();

        $category = new Category();

        $form = $this->createMock(FormInterface::class);
        $form->expects($this->once())->method('handleRequest');
        $form->method('isSubmitted')->willReturn(false);
        $form->method('isValid')->willReturn(false);

        $controller->expects($this->once())->method('createForm')->with(CategoryType::class, $category)->willReturn($form);
        $controller->expects($this->once())->method('render')
            ->with('admin/category/edit.html.twig', ['category' => $category, 'form' => $form])
            ->willReturn(new Response('edit'));

        $response = $controller->edit($category, new Request(), $this->createMock(EntityManagerInterface::class));

        $this->assertSame('edit', $response->getContent());
    }

    public function testDeleteRemovesCategoryAndRedirects(): void
    {
        $controller = $this->getMockBuilder(CategoryController::class)
            ->onlyMethods(['addFlash', 'redirectToRoute'])
            ->getMock();

        $em = $this->createMock(EntityManagerInterface::class);
        $category = new Category();

        $em->expects($this->once())->method('remove')->with($category);
        $em->expects($this->once())->method('flush');

        $controller->expects($this->once())->method('addFlash')->with('success', 'La catégorie a bien été supprimée');
        $controller->expects($this->once())->method('redirectToRoute')->with('admin.category.index')
            ->willReturn(new RedirectResponse('/admin/category/'));

        $response = $controller->delete($category, $em);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }
}

