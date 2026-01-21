<?php

namespace App\Tests\Repository;

use App\DTO\CategoryWithCountDTO;
use App\Entity\Category;
use App\Repository\CategoryRepository;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use PHPUnit\Framework\TestCase;

final class CategoryRepositoryTest extends TestCase
{
    public function testPaginateCategoryMapsToDto(): void
    {
        $paginator = $this->createMock(PaginatorInterface::class);

        $category = new Category();
        $category->setName('Cat');

        // set id
        $idProp = new \ReflectionProperty(Category::class, 'id');
        $idProp->setAccessible(true);
        $idProp->setValue($category, 1);

        $items = [
            [$category, 'recipeCount' => '3'],
        ];

        $pagination = $this->createMock(PaginationInterface::class);
        $pagination->expects($this->once())->method('getItems')->willReturn($items);
        $pagination->expects($this->once())
            ->method('setItems')
            ->with($this->callback(function (array $dtos) {
                return $dtos[0] instanceof CategoryWithCountDTO
                    && $dtos[0]->id === 1
                    && $dtos[0]->name === 'Cat'
                    && $dtos[0]->recipeCount === 3;
            }));

        $qb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('leftJoin')->willReturnSelf();
        $qb->method('groupBy')->willReturnSelf();

        $repo = $this->getMockBuilder(CategoryRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $repo->expects($this->once())
            ->method('createQueryBuilder')
            ->with('c')
            ->willReturn($qb);

        $paginator->expects($this->once())
            ->method('paginate')
            ->with($qb, 1, 10, $this->isType('array'))
            ->willReturn($pagination);

        $prop = new \ReflectionProperty(CategoryRepository::class, 'paginator');
        $prop->setAccessible(true);
        $prop->setValue($repo, $paginator);

        $result = $repo->paginateCategory(1);
        $this->assertSame($pagination, $result);
    }
}


