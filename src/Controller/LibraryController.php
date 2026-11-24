<?php

namespace App\Controller;

use App\Entity\Author;
use App\Entity\Book;
use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LibraryController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    #[Route('/books', name: 'app_book_index', methods: ['GET'])]
    public function books(
        Request $request,
        BookRepository $bookRepository,
        AuthorRepository $authorRepository,
    ): Response {
        $query = $request->query->getString('q');
        $genre = $request->query->getString('genre');
        $authorParam = $request->query->get('author');
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

        return $this->render('library/books.html.twig', [
            'books' => $books,
            'genres' => $bookRepository->findDistinctGenres(),
            'authors' => $authorRepository->findAllOrdered(),
            'filters' => [
                'q' => $query,
                'genre' => $genre,
                'author' => $authorId,
                'available' => $onlyAvailable,
                'sort' => $sort,
            ],
        ]);
    }

    #[Route('/books/{id}', name: 'app_book_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function bookShow(Book $book): Response
    {
        return $this->render('library/book_show.html.twig', [
            'book' => $book,
        ]);
    }

    #[Route('/authors', name: 'app_author_index', methods: ['GET'])]
    public function authors(AuthorRepository $authorRepository): Response
    {
        return $this->render('library/authors.html.twig', [
            'authorsWithCount' => $authorRepository->findAllWithBookCount(),
        ]);
    }

    #[Route('/authors/{id}', name: 'app_author_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function authorShow(Author $author): Response
    {
        return $this->render('library/author_show.html.twig', [
            'author' => $author,
        ]);
    }
}
