<?php

namespace App\Command;

use App\Entity\Author;
use App\Entity\Book;
use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-library',
    description: 'Наповнює каталог бібліотеки демонстраційними авторами та книгами (ідемпотентно).',
)]
final class SeedLibraryCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AuthorRepository $authorRepository,
        private readonly BookRepository $bookRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $authorsData = [
            'shevchenko' => [
                'fullName' => 'Тарас Шевченко',
                'country' => 'Україна',
                'birthYear' => 1814,
                'biography' => 'Український поет, прозаїк, мислитель і живописець. Основоположник нової української літератури.',
            ],
            'franko' => [
                'fullName' => 'Іван Франко',
                'country' => 'Україна',
                'birthYear' => 1856,
                'biography' => 'Видатний український письменник, поет, публіцист, перекладач, науковець і громадський діяч.',
            ],
            'ukrainka' => [
                'fullName' => 'Леся Українка',
                'country' => 'Україна',
                'birthYear' => 1871,
                'biography' => 'Українська письменниця, драматургиня та перекладачка, авторка «Лісової пісні» та збірок лірики.',
            ],
            'kostenko' => [
                'fullName' => 'Ліна Костенко',
                'country' => 'Україна',
                'birthYear' => 1930,
                'biography' => 'Українська поетеса-шістдесятниця, прозаїкиня, лауреатка Шевченківської премії.',
            ],
            'orwell' => [
                'fullName' => 'Джордж Орвелл',
                'country' => 'Велика Британія',
                'birthYear' => 1903,
                'biography' => 'Британський письменник, журналіст та есеїст, автор всесвітньо відомих антиутопій.',
            ],
            'martin' => [
                'fullName' => 'Роберт Мартін',
                'country' => 'США',
                'birthYear' => 1952,
                'biography' => 'Інженер програмного забезпечення, співзасновник Agile Manifesto та автор книг із чистого коду й архітектури.',
            ],
        ];

        $authorsMap = [];
        $createdAuthors = 0;

        foreach ($authorsData as $key => $data) {
            $author = $this->authorRepository->findOneBy(['fullName' => $data['fullName']]);
            if ($author === null) {
                $author = (new Author())
                    ->setFullName($data['fullName'])
                    ->setCountry($data['country'])
                    ->setBirthYear($data['birthYear'])
                    ->setBiography($data['biography']);
                $this->entityManager->persist($author);
                ++$createdAuthors;
            }
            $authorsMap[$key] = $author;
        }

        $booksData = [
            [
                'author' => 'shevchenko',
                'title' => 'Кобзар',
                'isbn' => '978-966-01-0512-3',
                'genre' => 'Поезія',
                'publishedYear' => 1840,
                'pages' => 720,
                'availableCopies' => 6,
                'description' => 'Збірка поетичних творів Тараса Шевченка, що стала духовним символом української культури.',
            ],
            [
                'author' => 'shevchenko',
                'title' => 'Гайдамаки',
                'isbn' => '978-966-01-0518-5',
                'genre' => 'Історична поема',
                'publishedYear' => 1841,
                'pages' => 168,
                'availableCopies' => 3,
                'description' => 'Історико-героїчна поема про події Коліївщини на Правобережній Україні.',
            ],
            [
                'author' => 'franko',
                'title' => 'Захар Беркут',
                'isbn' => '978-966-03-4821-9',
                'genre' => 'Історична повість',
                'publishedYear' => 1883,
                'pages' => 240,
                'availableCopies' => 5,
                'description' => 'Історична повість про боротьбу карпатської громади Тухлі проти монгольської навали XIII століття.',
            ],
            [
                'author' => 'franko',
                'title' => 'Перехресні стежки',
                'isbn' => '978-966-03-4829-5',
                'genre' => 'Соціальний роман',
                'publishedYear' => 1900,
                'pages' => 352,
                'availableCopies' => 2,
                'description' => 'Психологічний роман про адвоката Євгенія Рафаловича та боротьбу за справедливість у Галичині.',
            ],
            [
                'author' => 'ukrainka',
                'title' => 'Лісова пісня',
                'isbn' => '978-966-10-4112-6',
                'genre' => 'Драма-феєрія',
                'publishedYear' => 1911,
                'pages' => 144,
                'availableCopies' => 7,
                'description' => 'Шедевр української драматургії про гармонію людини і природи, кохання Лукаша та Мавки.',
            ],
            [
                'author' => 'ukrainka',
                'title' => 'Бояриня',
                'isbn' => '978-966-10-4118-8',
                'genre' => 'Драма',
                'publishedYear' => 1910,
                'pages' => 128,
                'availableCopies' => 0,
                'description' => 'Драматична поема про національну гідність і свободу особистості в історичному контексті XVII століття.',
            ],
            [
                'author' => 'kostenko',
                'title' => 'Маруся Чурай',
                'isbn' => '978-966-85-5801-4',
                'genre' => 'Історичний роман у віршах',
                'publishedYear' => 1979,
                'pages' => 224,
                'availableCopies' => 4,
                'description' => 'Роман у віршах про легендарну полтавську піснярку часів Хмельниччини.',
            ],
            [
                'author' => 'kostenko',
                'title' => 'Записки українського самашедшого',
                'isbn' => '978-966-85-5809-0',
                'genre' => 'Сучасна проза',
                'publishedYear' => 2010,
                'pages' => 416,
                'availableCopies' => 2,
                'description' => 'Перший прозовий роман Ліни Костенко — щоденник київського програміста на межі тисячоліть.',
            ],
            [
                'author' => 'orwell',
                'title' => '1984',
                'isbn' => '978-966-91-7045-3',
                'genre' => 'Антиутопія',
                'publishedYear' => 1949,
                'pages' => 312,
                'availableCopies' => 4,
                'description' => 'Класичний роман-антиутопія про тоталітарний контроль, пропаганду та боротьбу за правду.',
            ],
            [
                'author' => 'orwell',
                'title' => 'Колгосп тварин',
                'isbn' => '978-966-91-7052-1',
                'genre' => 'Сатира',
                'publishedYear' => 1945,
                'pages' => 128,
                'availableCopies' => 3,
                'description' => 'Політична алегорія про переродження революційних ідеалів у диктатуру.',
            ],
            [
                'author' => 'martin',
                'title' => 'Чистий код',
                'isbn' => '978-617-09-5288-2',
                'genre' => 'Програмування',
                'publishedYear' => 2008,
                'pages' => 464,
                'availableCopies' => 5,
                'description' => 'Практичний посібник зі створення читабельного, підтримуваного та надійного програмного коду.',
            ],
            [
                'author' => 'martin',
                'title' => 'Чиста архітектура',
                'isbn' => '978-617-09-5501-2',
                'genre' => 'Програмування',
                'publishedYear' => 2017,
                'pages' => 432,
                'availableCopies' => 3,
                'description' => 'Принципи проєктування масштабованих програмних систем, SOLID та розділення шарів додатку.',
            ],
        ];

        $createdBooks = 0;

        foreach ($booksData as $data) {
            if ($this->bookRepository->findOneBy(['isbn' => $data['isbn']]) !== null) {
                continue;
            }

            $book = (new Book())
                ->setTitle($data['title'])
                ->setIsbn($data['isbn'])
                ->setGenre($data['genre'])
                ->setPublishedYear($data['publishedYear'])
                ->setPages($data['pages'])
                ->setAvailableCopies($data['availableCopies'])
                ->setDescription($data['description'])
                ->setAuthor($authorsMap[$data['author']]);

            $this->entityManager->persist($book);
            ++$createdBooks;
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            'Наповнення завершено: додано авторів — %d, книг — %d.',
            $createdAuthors,
            $createdBooks,
        ));

        return Command::SUCCESS;
    }
}
