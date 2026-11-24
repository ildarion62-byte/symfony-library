<?php

namespace App\Repository;

use App\Entity\Author;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Author>
 */
class AuthorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Author::class);
    }

    /**
     * Returns all authors ordered by full name with their book count using QueryBuilder.
     *
     * @return array<int, array{author: Author, bookCount: int}>
     */
    public function findAllWithBookCount(): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('a AS author', 'COUNT(b.id) AS bookCount')
            ->leftJoin('a.books', 'b')
            ->groupBy('a.id')
            ->orderBy('a.fullName', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (array $row): array => [
                'author' => $row['author'],
                'bookCount' => (int) $row['bookCount'],
            ],
            $rows,
        );
    }

    /**
     * @return Author[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.fullName', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
