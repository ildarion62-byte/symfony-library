# Конспект основних концепцій Symfony (для підготовки до захисту)

> **Примітка:** цей файл містить структурований огляд архітектури та тем курсу, реалізованих у проєкті **«Книжкова бібліотека»**. 

---

## 1. Архітектура Symfony та життєвий цикл HTTP-запиту

Symfony реалізує патерн **Front Controller** (`public/index.php`) та архітектуру **MVC**:
1. Клієнт (браузер або Python-клієнт) надсилає HTTP-запит.
2. `public/index.php` створює об'єкт `Request` і передає його в `Kernel`.
3. **Router** зіставляє URL і HTTP-метод із маршрутом (атрибут `#[Route('/books/{id}')]`).
4. **Security Firewall** перевіряє правила доступу (`access_control` у `config/packages/security.yaml` та атрибут `#[IsGranted('ROLE_ADMIN')]`).
5. **Controller** отримує залежності через **Dependency Injection** (репозиторії, `EntityManagerInterface`), обробляє дані та повертає `Response` (HTML із Twig або `JsonResponse`).

---

## 2. Doctrine ORM, зв'язки сутностей та міграції

- **Entity (Сутність)** — PHP-клас, що відображається на таблицю БД за допомогою PHP-атрибутів `#[ORM\Entity]`, `#[ORM\Column]`, `#[ORM\Id]`.
- У проєкті реалізовано зв'язок **OneToMany / ManyToOne**:
  - `Author` (один автор) має колекцію `books` (`#[ORM\OneToMany(targetEntity: Book::class, mappedBy: 'author')]`).
  - `Book` (багато книг) посилається на автора (`#[ORM\ManyToOne(inversedBy: 'books')]`, `#[ORM\JoinColumn(nullable: false)]`) — це **owning side** (власник зв'язку), у таблиці `book` створюється зовнішній ключ `author_id`.
- **Doctrine Migrations**:
  - `php bin/console make:migration` порівнює поточні сутності зі схемою БД і генерує PHP-клас міграції з методами `up()` та `down()`.
  - `php bin/console doctrine:migrations:migrate` виконує нові міграції та записує їхній статус у таблицю `doctrine_migration_versions`.
  - Переваги перед ручним SQL: версіонування схеми у Git, відтворюваність на будь-якому середовищі, можливість відкату (`down`).

---

## 3. Doctrine QueryBuilder

`QueryBuilder` дозволяє будувати типобезпечні динамічні DQL-запити без конкатенації сирого SQL та із захистом від SQL-ін'єкцій через іменовані параметри (`setParameter()`).

У проєкті (`src/Repository/BookRepository.php`):
- `innerJoin('b.author', 'a')->addSelect('a')` — завантажує автора за один запит (уникнення проблеми **N+1**);
- умовні `andWhere(...)` для пошуку (`q`), жанру (`genre`), автора (`authorId`) та наявності (`b.availableCopies > 0`);
- динамічне сортування (`orderBy`).

---

## 4. Шаблонізатор Twig

- Наслідування шаблонів: `{% extends 'base.html.twig' %}` та перевизначення блоків `{% block body %}`.
- Автоматичне екранування змінних `{{ book.title }}` для захисту від **XSS**.
- Генерація URL за іменем маршруту: `{{ path('app_book_show', { id: book.id }) }}`.
- Автоматичне перетворення параметра `{id}` з URL в об'єкт `Book` у контролері (`EntityValueResolver`) — якщо запису немає, Symfony автоматично повертає `404 Not Found`.

---

## 5. Валідація даних (`symfony/validator`)

Обмеження задаються декларативно атрибутами на властивостях сутностей:
- `#[Assert\NotBlank]`, `#[Assert\Length]` — перевірка непорожніх рядків та довжини;
- `#[Assert\Range(min: 1450, max: 2030)]` — діапазон числових полів;
- `#[Assert\Regex]` — формат ISBN;
- `#[UniqueEntity(fields: ['isbn'])]` — унікальність на рівні додатку до вставки в БД.

EasyAdmin автоматично запускає валідатор при створенні й редагуванні записів у CRUD-формах.

---

## 6. Symfony Security: автентифікація, авторизація та CSRF

- **User Provider** (`app_user_provider` у `security.yaml`) завантажує сутність `App\Entity\User` з БД за полем `email`.
- **Password Hasher** (`algorithm: auto`) зберігає паролі лише у вигляді криптографічного хешу (bcrypt/argon2id), ніколи у відкритому вигляді.
- **Консольна команда `app:create-admin`** створює обліковий запис із роллю `ROLE_ADMIN`, приховано зчитуючи пароль (`askHidden`). У Git-репозиторії паролі відсутні.
- **Авторизація**:
  - у `security.yaml`: `access_control: - { path: ^/admin, roles: ROLE_ADMIN }`;
  - у контролерах EasyAdmin: `#[IsGranted('ROLE_ADMIN')]`.
- **CSRF-захист**: форма `/login` містить приховане поле `_csrf_token` (`csrf_token('authenticate')`), яке прив'язане до HTTP-сесії та перевіряється при `POST /login`.

---

## 7. EasyAdmin Bundle

- `DashboardController` із атрибутом `#[AdminDashboard(routePath: '/admin', routeName: 'admin')]` задає головну панель і бічне меню.
- `BookCrudController` та `AuthorCrudController` наслідують `AbstractCrudController` і конфігурують поля (`TextField`, `IntegerField`, `TextareaField`, `AssociationField`) та фільтри (`EntityFilter`, `TextFilter`, `NumericFilter`).
- Метод `__toString()` у сутності `Author` забезпечує читабельне відображення автора у випадаючому списку `AssociationField`.

---

## 8. JSON API та Python-клієнт

- `ApiController` повертає `JsonResponse` з прапором `JSON_UNESCAPED_UNICODE` для ендпоінтів `/api/books`, `/api/books/{id}`, `/api/authors`, `/api/authors/{id}`.
- Python-клієнт (`client/library_client.py`) використовує бібліотеку `requests`:
  - `requests.get(...)` для читання та фільтрації JSON API;
  - `requests.Session()` зберігає сесійний cookie (`PHPSESSID`) між запитами: спочатку `GET /login` отримує cookie та `_csrf_token`, далі `POST /login` виконує автентифікацію, після чого сесія отримує доступ `200 OK` до захищених сторінок `/admin` та `/admin/book`, а після `GET /logout` знову отримує редирект `302` на `/login`.
