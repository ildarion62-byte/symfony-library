<?php

namespace App\Tests;

use App\Entity\Author;
use App\Entity\Book;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class LibraryWebTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Author $author;
    private Book $availableBook;
    private Book $unavailableBook;
    private string $testAdminEmail = 'test-admin@library.local';
    private string $testAdminPass;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $this->em = $em;

        $config = $this->em->getConfiguration();
        if (method_exists($config, 'setUseSchemaEditorApi')) {
            $config->setUseSchemaEditorApi(true);
        }

        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->em);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $this->author = (new Author())
            ->setFullName('Тарас Шевченко')
            ->setCountry('Україна')
            ->setBirthYear(1814)
            ->setBiography('Український поет і художник.');

        $this->availableBook = (new Book())
            ->setTitle('Кобзар')
            ->setIsbn('978-966-01-0512-3')
            ->setGenre('Поезія')
            ->setPublishedYear(1840)
            ->setPages(720)
            ->setAvailableCopies(5)
            ->setDescription('Збірка поетичних творів.')
            ->setAuthor($this->author);

        $this->unavailableBook = (new Book())
            ->setTitle('Гайдамаки')
            ->setIsbn('978-966-01-0518-5')
            ->setGenre('Історична поема')
            ->setPublishedYear(1841)
            ->setPages(168)
            ->setAvailableCopies(0)
            ->setDescription('Історична поема.')
            ->setAuthor($this->author);

        $this->testAdminPass = bin2hex(random_bytes(8));

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);
        $admin = (new User())
            ->setEmail($this->testAdminEmail)
            ->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($hasher->hashPassword($admin, $this->testAdminPass));

        $this->em->persist($this->author);
        $this->em->persist($this->availableBook);
        $this->em->persist($this->unavailableBook);
        $this->em->persist($admin);
        $this->em->flush();
    }

    public function testTwigCatalogAndBookDetailPage(): void
    {
        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Каталог книг');
        self::assertStringContainsString('Кобзар', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/books/' . $this->availableBook->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Кобзар');
        self::assertStringContainsString('978-966-01-0512-3', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/authors/' . $this->author->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Тарас Шевченко');
    }

    public function testQueryBuilderSearchAndAvailabilityFilter(): void
    {
        $this->client->request('GET', '/api/books?q=кобзар');
        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(1, $data['count']);
        self::assertSame('Кобзар', $data['items'][0]['title']);

        $this->client->request('GET', '/api/books?available=1');
        self::assertResponseIsSuccessful();
        $availData = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(1, $availData['count']);
        self::assertTrue($availData['items'][0]['available']);
    }

    public function testJsonApiSingleItemAnd404(): void
    {
        $this->client->request('GET', '/api/books/' . $this->availableBook->getId());
        self::assertResponseIsSuccessful();
        $book = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Кобзар', $book['title']);
        self::assertSame('Тарас Шевченко', $book['author']['fullName']);

        $this->client->request('GET', '/api/books/999999');
        self::assertResponseStatusCodeSame(404);
    }

    public function testAdminAccessControlAndSessionLogin(): void
    {
        $this->client->request('GET', '/admin');
        self::assertResponseRedirects('/login');

        $crawler = $this->client->request('GET', '/login');
        self::assertResponseIsSuccessful();

        $csrfToken = $crawler->filter('input[name="_csrf_token"]')->attr('value');
        self::assertNotEmpty($csrfToken);

        $this->client->request('POST', '/login', [
            '_username' => $this->testAdminEmail,
            '_password' => $this->testAdminPass,
            '_csrf_token' => $csrfToken,
        ]);
        self::assertResponseRedirects('/admin');

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Панель керування бібліотекою', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/admin/book');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Кобзар', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/admin/author');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Тарас Шевченко', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/admin/book/new');
        self::assertResponseIsSuccessful();
    }

    public function testBookEntityValidation(): void
    {
        /** @var ValidatorInterface $validator */
        $validator = static::getContainer()->get(ValidatorInterface::class);

        $invalidBook = (new Book())
            ->setTitle('')
            ->setIsbn('bad')
            ->setGenre('A')
            ->setPublishedYear(1200)
            ->setPages(0)
            ->setAvailableCopies(-3);

        $violations = $validator->validate($invalidBook);
        self::assertGreaterThanOrEqual(6, count($violations));
    }
}
