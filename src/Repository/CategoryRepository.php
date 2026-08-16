<?php

namespace App\Repository;

use App\DTO\CategoryWithCountDTO;
use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private PaginatorInterface $paginator)
    {
        parent::__construct($registry, Category::class);
    }

    public function paginateCategory(int $page): PaginationInterface
    {

        // Creating the QueryBuilder (KNP can't use SQL aliasing)
        $qb = $this->createQueryBuilder('c')
            ->select('c', 'COUNT(r.id) AS recipeCount')
            ->leftJoin('c.recipes', 'r')
            ->groupBy('c.id');

        // use KNP for sorting and paginating in SQL
        $pagination = $this->paginator->paginate(
            $qb,
            $page,
            10,
            [
                'defaultSortFieldName' => 'c.name',        // tri par défaut
                'defaultSortDirection' => 'asc'
            ]
        );

        // Replacing Entity and count by clean DTO
        $pagination->setItems(array_map(
            fn($row) => new CategoryWithCountDTO(
                $row[0]->getId(),
                $row[0]->getName(),
                (int) $row['recipeCount']
            ),
            $pagination->getItems()
        ));

        return $pagination;

    }

    /**
     * @return PaginationInterface<int, array{0: Category, recipeCount: int|string}>
     */
    public function paginateCategoriesForApi(int $page, int $perPage = 10): PaginationInterface
    {
        $query = $this->createQueryBuilder('category')
            ->select('category', 'COUNT(recipe.id) AS recipeCount')
            ->leftJoin('category.recipes', 'recipe')
            ->groupBy('category.id')
            ->orderBy('category.name', 'ASC');

        return $this->paginator->paginate(
            $query,
            max(1, $page),
            max(1, $perPage),
        );
    }

    /**
     * @return array{0: Category, recipeCount: int|string}|null
     */
    public function findOneWithRecipeCountBySlug(string $slug): ?array
    {
        return $this->createQueryBuilder('category')
            ->select('category', 'COUNT(recipe.id) AS recipeCount')
            ->leftJoin('category.recipes', 'recipe')
            ->andWhere('category.slug = :slug')
            ->setParameter('slug', $slug)
            ->groupBy('category.id')
            ->getQuery()
            ->getOneOrNullResult();
    }



    //    /**
    //     * @return Category[] Returns an array of Category objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Category
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
