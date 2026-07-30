<?php

namespace App\Repository;

use App\Entity\Recipe;
use App\Entity\User;
use App\Enum\RecipeVisibility;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;

/**
 * @extends ServiceEntityRepository<Recipe>
 */
class RecipeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private PaginatorInterface $paginator)
    {
        parent::__construct($registry, Recipe::class);
    }

    public function paginateRecipes(int $page): PaginationInterface
    {

        return $this->paginator->paginate(
            $this->createQueryBuilder('r')->leftJoin('r.category', 'c')->select('r', 'c'),
            $page,
            10
        );

    }

    public function paginatePublicRecipes(int $page): PaginationInterface
    {
        $query = $this->createQueryBuilder('recipe')
            ->leftJoin('recipe.category', 'category')
            ->addSelect('category')
            ->andWhere('recipe.visibility = :visibility')
            ->setParameter('visibility', RecipeVisibility::PUBLIC->value);

        return $this->paginator->paginate($query, max(1, $page), 10);
    }

    public function privatizeAndDetachByCreator(User $user): int
    {
        return $this->createQueryBuilder('recipe')
            ->update()
            ->set('recipe.visibility', ':visibility')
            ->set('recipe.createdBy', 'NULL')
            ->andWhere('recipe.createdBy = :creator')
            ->setParameter('visibility', RecipeVisibility::PRIVATE->value)
            ->setParameter('creator', $user)
            ->getQuery()
            ->execute();
    }

    /**
     * Get the Recipes where the duration is lower than an amount of minutes
     * @param int $duration
     * @return Recipe[]
     */
    // public function findWithDurationLowerThan(int $duration): array
    // {

    //     return $this->createQueryBuilder('r')
    //         ->where('r.duration <= :duration')
    //         ->orderBy('r.duration', 'ASC')
    //         ->setMaxResults(10)
    //         ->setParameter('duration', $duration)
    //         ->getQuery()
    //         ->getResult();

    // }

    //    /**
    //     * @return Recipe[] Returns an array of Recipe objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('r.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Recipe
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
