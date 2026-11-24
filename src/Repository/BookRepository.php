<?php

namespace App\Repository;

use App\Entity\Book;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Book>
 */
class BookRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Book::class);
    }

    /**
     * Searches and filters books using Doctrine QueryBuilder.
     *
     * @return Book[]
     */
    public function findByFilter(
        ?string $query = null,
        ?string $genre = null,
        ?int $authorId = null,
        bool $onlyAvailable = false,
        string $sort = 'title',
    ): array {
        $qb = $this->createQueryBuilder('b')
            ->innerJoin('b.author', 'a')
            ->addSelect('a');

        if ($query !== null && trim($query) !== '') {
            $clean = trim($query);
            $termOrig = '%' . $clean . '%';
            $termLower = '%' . mb_strtolower($clean) . '%';
            $termCap = '%' . mb_strtoupper(mb_substr($clean, 0, 1)) . mb_strtolower(mb_substr($clean, 1)) . '%';

            $qb->andWhere(
                'LOWER(b.title) LIKE :termLower OR b.title LIKE :termOrig OR b.title LIKE :termCap '
                . 'OR LOWER(b.isbn) LIKE :termLower '
                . 'OR LOWER(a.fullName) LIKE :termLower OR a.fullName LIKE :termOrig OR a.fullName LIKE :termCap '
                . 'OR LOWER(b.genre) LIKE :termLower OR b.genre LIKE :termOrig OR b.genre LIKE :termCap'
            )
                ->setParameter('termLower', $termLower)
                ->setParameter('termOrig', $termOrig)
                ->setParameter('termCap', $termCap);
        }

        if ($genre !== null && trim($genre) !== '') {
            $qb->andWhere('b.genre = :genre')
                ->setParameter('genre', trim($genre));
        }

        if ($authorId !== null && $authorId > 0) {
            $qb->andWhere('a.id = :authorId')
                ->setParameter('authorId', $authorId);
        }

        if ($onlyAvailable) {
            $qb->andWhere('b.availableCopies > 0');
        }

        match ($sort) {
            'year_desc' => $qb->orderBy('b.publishedYear', 'DESC')->addOrderBy('b.title', 'ASC'),
            'year_asc' => $qb->orderBy('b.publishedYear', 'ASC')->addOrderBy('b.title', 'ASC'),
            'pages_desc' => $qb->orderBy('b.pages', 'DESC')->addOrderBy('b.title', 'ASC'),
            'author' => $qb->orderBy('a.fullName', 'ASC')->addOrderBy('b.title', 'ASC'),
            default => $qb->orderBy('b.title', 'ASC'),
        };

        return $qb->getQuery()->getResult();
    }

    /**
     * Returns sorted list of unique genres in the catalog.
     *
     * @return string[]
     */
    public function findDistinctGenres(): array
    {
        $rows = $this->createQueryBuilder('b')
            ->select('DISTINCT b.genre')
            ->orderBy('b.genre', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_values(array_column($rows, 'genre'));
    }
}
