<?php

namespace App\Tests\Repository;

use App\Repository\RecipeRepository;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use PHPUnit\Framework\TestCase;

final class RecipeRepositoryTest extends TestCase
{
    public function testPaginateRecipesUsesPaginator(): void
    {
        $paginator = $this->createMock(PaginatorInterface::class);
        $pagination = $this->createMock(PaginationInterface::class);

        $qb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qb->method('leftJoin')->willReturnSelf();
        $qb->method('select')->willReturnSelf();

        $repo = $this->getMockBuilder(RecipeRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $repo->expects($this->once())
            ->method('createQueryBuilder')
            ->with('r')
            ->willReturn($qb);

        $paginator->expects($this->once())
            ->method('paginate')
            ->with($qb, 2, 10)
            ->willReturn($pagination);

        $prop = new \ReflectionProperty(RecipeRepository::class, 'paginator');
        $prop->setAccessible(true);
        $prop->setValue($repo, $paginator);

        $result = $repo->paginateRecipes(2);
        $this->assertSame($pagination, $result);
    }
}


