<?php

namespace App\Entity;

use App\Repository\AuthorRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AuthorRepository::class)]
class Author
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: 'Ім’я автора є обов’язковим.')]
    #[Assert\Length(
        min: 2,
        max: 150,
        minMessage: 'Ім’я автора має містити щонайменше {{ limit }} символи.',
        maxMessage: 'Ім’я автора не може перевищувати {{ limit }} символів.',
    )]
    private ?string $fullName = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Країна автора є обов’язковою.')]
    #[Assert\Length(min: 2, max: 100)]
    private ?string $country = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(
        min: 1000,
        max: 2025,
        notInRangeMessage: 'Рік народження має бути між {{ min }} та {{ max }}.',
    )]
    private ?int $birthYear = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000)]
    private ?string $biography = null;

    /**
     * @var Collection<int, Book>
     */
    #[ORM\OneToMany(targetEntity: Book::class, mappedBy: 'author', orphanRemoval: true)]
    #[ORM\OrderBy(['publishedYear' => 'ASC', 'title' => 'ASC'])]
    private Collection $books;

    public function __construct()
    {
        $this->books = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): static
    {
        $this->fullName = trim($fullName);

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(string $country): static
    {
        $this->country = trim($country);

        return $this;
    }

    public function getBirthYear(): ?int
    {
        return $this->birthYear;
    }

    public function setBirthYear(?int $birthYear): static
    {
        $this->birthYear = $birthYear;

        return $this;
    }

    public function getBiography(): ?string
    {
        return $this->biography;
    }

    public function setBiography(?string $biography): static
    {
        $this->biography = $biography;

        return $this;
    }

    /**
     * @return Collection<int, Book>
     */
    public function getBooks(): Collection
    {
        return $this->books;
    }

    public function addBook(Book $book): static
    {
        if (!$this->books->contains($book)) {
            $this->books->add($book);
            $book->setAuthor($this);
        }

        return $this;
    }

    public function removeBook(Book $book): static
    {
        if ($this->books->removeElement($book)) {
            if ($book->getAuthor() === $this) {
                $book->setAuthor(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->fullName ?? '';
    }
}
