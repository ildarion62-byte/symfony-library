<?php

namespace App\Controller;

use App\Entity\Author;
use App\Entity\Book;
use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_')]
final class ApiController extends AbstractController
{
    #[Route('/books', name: 'books_index', methods: ['GET'])]
    public function booksIndex(Request $request, BookRepository $bookRepository): JsonResponse
    {
        $query = $request->query->getString('q');
        $genre = $request->query->getString('genre');
        $authorParam = $request->query->get('author_id');
        $authorId = is_numeric($authorParam) ? (int) $authorParam : null;
        $onlyAvailable = $request->query->getBoolean('available');
        $sort = $request->query->getString('sort', 'title');

        $books = $bookRepository->findByFilter(
            query: $query !== '' ? $query : null,
            genre: $genre !== '' ? $genre : null,
            authorId: $authorId,
            onlyAvailable: $onlyAvailable,
            sort: $sort,
        );

        return $this->jsonResponse([
            'count' => count($books),
            'filters' => [
                'q' => $query !== '' ? $query : null,
                'genre' => $genre !== '' ? $genre : null,
                'author_id' => $authorId,
                'available' => $onlyAvailable,
                'sort' => $sort,
            ],
            'items' => array_map($this->serializeBook(...), $books),
        ]);
    }

    #[Route('/books/{id}', name: 'books_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function booksShow(int $id, BookRepository $bookRepository): JsonResponse
    {
        $book = $bookRepository->find($id);
        if ($book === null) {
            return $this->jsonResponse(
                ['error' => sprintf('Книгу з id=%d не знайдено.', $id)],
                Response::HTTP_NOT_FOUND,
            );
        }

        return $this->jsonResponse($this->serializeBook($book, includeAuthorBio: true));
    }

    #[Route('/authors', name: 'authors_index', methods: ['GET'])]
    public function authorsIndex(AuthorRepository $authorRepository): JsonResponse
    {
        $rows = $authorRepository->findAllWithBookCount();

        $items = array_map(
            static function (array $row): array {
                /** @var Author $author */
                $author = $row['author'];

                return [
                    'id' => $author->getId(),
                    'fullName' => $author->getFullName(),
                    'country' => $author->getCountry(),
                    'birthYear' => $author->getBirthYear(),
                    'biography' => $author->getBiography(),
                    'bookCount' => $row['bookCount'],
                ];
            },
            $rows,
        );

        return $this->jsonResponse([
            'count' => count($items),
            'items' => $items,
        ]);
    }

    #[Route('/authors/{id}', name: 'authors_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function authorsShow(int $id, AuthorRepository $authorRepository): JsonResponse
    {
        $author = $authorRepository->find($id);
        if ($author === null) {
            return $this->jsonResponse(
                ['error' => sprintf('Автора з id=%d не знайдено.', $id)],
                Response::HTTP_NOT_FOUND,
            );
        }

        $books = [];
        foreach ($author->getBooks() as $book) {
            $books[] = [
                'id' => $book->getId(),
                'title' => $book->getTitle(),
                'isbn' => $book->getIsbn(),
                'genre' => $book->getGenre(),
                'publishedYear' => $book->getPublishedYear(),
                'pages' => $book->getPages(),
                'availableCopies' => $book->getAvailableCopies(),
                'available' => $book->isAvailable(),
            ];
        }

        return $this->jsonResponse([
            'id' => $author->getId(),
            'fullName' => $author->getFullName(),
            'country' => $author->getCountry(),
            'birthYear' => $author->getBirthYear(),
            'biography' => $author->getBiography(),
            'books' => $books,
        ]);
    }

    #[Route('/genres', name: 'genres_index', methods: ['GET'])]
    public function genresIndex(BookRepository $bookRepository): JsonResponse
    {
        $genres = $bookRepository->findDistinctGenres();

        return $this->jsonResponse([
            'count' => count($genres),
            'items' => $genres,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeBook(Book $book, bool $includeAuthorBio = false): array
    {
        $author = $book->getAuthor();
        $authorData = $author !== null ? [
            'id' => $author->getId(),
            'fullName' => $author->getFullName(),
            'country' => $author->getCountry(),
            'birthYear' => $author->getBirthYear(),
        ] : null;

        if ($includeAuthorBio && $author !== null && $authorData !== null) {
            $authorData['biography'] = $author->getBiography();
        }

        return [
            'id' => $book->getId(),
            'title' => $book->getTitle(),
            'isbn' => $book->getIsbn(),
            'genre' => $book->getGenre(),
            'publishedYear' => $book->getPublishedYear(),
            'pages' => $book->getPages(),
            'availableCopies' => $book->getAvailableCopies(),
            'available' => $book->isAvailable(),
            'description' => $book->getDescription(),
            'author' => $authorData,
        ];
    }

    private function jsonResponse(mixed $data, int $status = Response::HTTP_OK): JsonResponse
    {
        $response = new JsonResponse($data, $status);
        $response->setEncodingOptions(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return $response;
    }
}
