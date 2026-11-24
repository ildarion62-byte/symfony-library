<?php

namespace App\Entity;

use App\Repository\BookRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BookRepository::class)]
#[UniqueEntity(fields: ['isbn'], message: 'Книга з таким ISBN вже зареєстрована у каталозі.')]
class Book
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank(message: 'Назва книги є обов’язковою.')]
    #[Assert\Length(
        min: 1,
        max: 200,
        maxMessage: 'Назва книги не може перевищувати {{ limit }} символів.',
    )]
    private ?string $title = null;

    #[ORM\Column(length: 20, unique: true)]
    #[Assert\NotBlank(message: 'ISBN є обов’язковим.')]
    #[Assert\Regex(
        pattern: '/^[0-9Xx\-]{10,20}$/',
        message: 'ISBN має містити від 10 до 20 цифр або дефісів (наприклад, 978-966-01-0512-3).',
    )]
    private ?string $isbn = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank(message: 'Жанр книги є обов’язковим.')]
    #[Assert\Length(min: 2, max: 80)]
    private ?string $genre = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Рік видання є обов’язковим.')]
    #[Assert\Range(
        min: 1450,
        max: 2030,
        notInRangeMessage: 'Рік видання має бути між {{ min }} та {{ max }}.',
    )]
    private ?int $publishedYear = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Кількість сторінок є обов’язковою.')]
    #[Assert\Range(
        min: 1,
        max: 10000,
        notInRangeMessage: 'Кількість сторінок має бути від {{ min }} до {{ max }}.',
    )]
    private ?int $pages = null;

    #[ORM\Column]
    #[Assert\NotNull]
    #[Assert\Range(
        min: 0,
        max: 500,
        notInRangeMessage: 'Кількість примірників має бути від {{ min }} до {{ max }}.',
    )]
    private int $availableCopies = 1;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 3000)]
    private ?string $description = null;

    #[ORM\ManyToOne(inversedBy: 'books')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Необхідно обрати автора книги.')]
    private ?Author $author = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = trim($title);

        return $this;
    }

    public function getIsbn(): ?string
    {
        return $this->isbn;
    }

    public function setIsbn(string $isbn): static
    {
        $this->isbn = trim($isbn);

        return $this;
    }

    public function getGenre(): ?string
    {
        return $this->genre;
    }

    public function setGenre(string $genre): static
    {
        $this->genre = trim($genre);

        return $this;
    }

    public function getPublishedYear(): ?int
    {
        return $this->publishedYear;
    }

    public function setPublishedYear(int $publishedYear): static
    {
        $this->publishedYear = $publishedYear;

        return $this;
    }

    public function getPages(): ?int
    {
        return $this->pages;
    }

    public function setPages(int $pages): static
    {
        $this->pages = $pages;

        return $this;
    }

    public function getAvailableCopies(): int
    {
        return $this->availableCopies;
    }

    public function setAvailableCopies(int $availableCopies): static
    {
        $this->availableCopies = $availableCopies;

        return $this;
    }

    public function isAvailable(): bool
    {
        return $this->availableCopies > 0;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getAuthor(): ?Author
    {
        return $this->author;
    }

    public function setAuthor(?Author $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function __toString(): string
    {
        return $this->title ?? '';
    }
}
