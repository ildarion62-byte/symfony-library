# Книжкова бібліотека — веб-сервіс на Symfony

Веб-сервіс для обліку та перегляду книжкового каталогу бібліотеки, реалізований на **Symfony 8.1**, **Doctrine ORM + Migrations**, **Twig**, **EasyAdmin 5**, **Symfony Security** та консольному клієнті на **Python**.

## Тема проєкту

**«Книжкова бібліотека»** — електронний каталог книг та авторів:
- публічний сайт на Twig для читачів (пошук, фільтрація, перегляд окремих книг та авторів);
- захищена адмін-панель EasyAdmin (`ROLE_ADMIN`) для повного CRUD-управління авторами й книгами;
- JSON API для інтеграції із зовнішніми клієнтами;
- консольний клієнт на Python, що читає дані з JSON API та вміє авторизовуватися в адмінці через HTTP-сесію.

## Реалізовані вимоги

### Обов'язкові
1. **Doctrine ORM + Міграції**:
   - пов'язані сутності `Author` (`OneToMany`) та `Book` (`ManyToOne`), а також `User` для автентифікації;
   - таблиці, зовнішні ключі й унікальні індекси створюються міграцією (`migrations/Version20261124151505.php`), а не ручним SQL.
2. **Публічний сайт на Twig**:
   - список книг (`/`, `/books`) та сторінка однієї книги з параметром у URL (`/books/{id}`);
   - список авторів (`/authors`) та сторінка автора з його книгами (`/authors/{id}`).
3. **Адмінка EasyAdmin + Безпека**:
   - CRUD-контролери `BookCrudController` та `AuthorCrudController` і дашборд `/admin`;
   - доступ до `/admin` закритий формою входу `/login` і вимагає `ROLE_ADMIN`;
   - адміністратор створюється консольною командою `php bin/console app:create-admin` (пароль запитується інтерактивно приховано, у репозиторій не потрапляє).
4. **Консольний клієнт на Python**:
   - `client/library_client.py` читає дані з JSON API (`/api/books`, `/api/books/{id}`, `/api/authors`, `/api/authors/{id}`).

### Додаткові (бонусні)
- **Вхід Python-клієнта в адмінку через сесію**: підкоманда `admin-demo` (`requests.Session` отримує `_csrf_token` зі сторінки `/login`, виконує `POST /login`, перевіряє доступ до `/admin` і `/admin/book`, після чого виходить через `/logout`).
- **Валідація полів (`symfony/validator`)**: атрибути `#[Assert\NotBlank]`, `#[Assert\Length]`, `#[Assert\Range]`, `#[Assert\Regex]`, `#[UniqueEntity]` для `Book`, `Author` та `User`.
- **Пошук і фільтрація через Doctrine `QueryBuilder`**: `BookRepository::findByFilter()` (пошук за назвою/автором/ISBN/жанром, фільтр за жанром, автором, наявністю примірників, сортування) — використовується одночасно у Twig-каталозі та JSON API.

## Вимоги до середовища

- PHP 8.2+ (перевірено на PHP 8.5) з увімкненими розширеннями `pdo_sqlite` (або `pdo_mysql`/`pdo_pgsql`), `intl`, `mbstring`, `openssl`
- Composer 2
- Python 3.10+ (`requests`)
- (Опційно) Symfony CLI

> **Примітка для Windows:** якщо в `php.ini` ще не розкоментовані розширення `pdo_sqlite` та `intl`, увімкніть `extension=pdo_sqlite`, `extension=sqlite3`, `extension=intl` у `php.ini` або передавайте їх через `-d extension=pdo_sqlite -d extension=sqlite3 -d extension=intl`.

## Як запустити проєкт

### 1. Клонування та встановлення PHP-залежностей

```bash
git clone <URL_вашого_репозиторію>
cd symfony-library
composer install
```

### 2. Локальна конфігурація (`.env.local`)

За замовчуванням у `.env` налаштовано SQLite (`var/data_dev.db`), тому додатковий сервер БД не потрібен.
Створіть локальний файл `.env.local` (він доданий у `.gitignore` і не потрапляє в Git) та задайте випадковий `APP_SECRET`:

```env
APP_SECRET=згенеруйте_випадковий_рядок_32_символи
# За бажанням можна перевизначити DATABASE_URL для PostgreSQL або MySQL:
# DATABASE_URL="postgresql://user:pass@127.0.0.1:5432/library?serverVersion=16&charset=utf8"
```

### 3. Створення таблиць міграціями

```bash
php bin/console doctrine:migrations:migrate
```

### 4. Наповнення демонстраційними даними (за бажанням)

```bash
php bin/console app:seed-library
```

Команда додасть 6 авторів та 12 книг (ідемпотентно — повторний запуск не створює дублікатів).

### 5. Створення адміністратора (`ROLE_ADMIN`)

```bash
php bin/console app:create-admin
```

Команда інтерактивно запитає email та прихований пароль (мінімум 8 символів) і збереже хешований пароль у БД.
Для оновлення пароля існуючого адміна використайте:

```bash
php bin/console app:create-admin admin@library.local --update
```

### 6. Запуск веб-сервера

Через Symfony CLI:

```bash
symfony serve
```

Або вбудованим сервером PHP (якщо Symfony CLI не встановлено):

```bash
php -S 127.0.0.1:8000 -t public
```

Після запуску відкрийте у браузері:
- Публічний каталог: `http://127.0.0.1:8000/`
- Автори: `http://127.0.0.1:8000/authors`
- Вхід: `http://127.0.0.1:8000/login`
- Адмінка EasyAdmin: `http://127.0.0.1:8000/admin`
- JSON API: `http://127.0.0.1:8000/api/books`

## Консольний клієнт на Python

Встановлення залежностей клієнта:

```bash
pip install -r client/requirements.txt
```

### Приклади команд

```bash
# Інтерактивне меню
python client/library_client.py

# Список усіх книг із JSON API
python client/library_client.py books

# Пошук і фільтрація через QueryBuilder
python client/library_client.py books --q шевченко
python client/library_client.py books --genre Програмування --available

# Деталі однієї книги за ID
python client/library_client.py book 1

# Автори та книги конкретного автора
python client/library_client.py authors
python client/library_client.py author 1

# Демо входу в захищену адмінку EasyAdmin через HTTP-сесію (пароль запитає приховано)
python client/library_client.py admin-demo --email admin@library.local
```

Якщо сервер запущено на іншому порту, передайте `--base-url http://127.0.0.1:8000`.

## Маршрути додатку

| Маршрут | Метод | Опис |
|---|---|---|
| `/` або `/books` | GET | Каталог книг із формою пошуку та фільтрів (Twig) |
| `/books/{id}` | GET | Сторінка однієї книги (Twig) |
| `/authors` | GET | Список авторів із кількістю книг (Twig) |
| `/authors/{id}` | GET | Сторінка автора та список його книг (Twig) |
| `/login` | GET, POST | Форма автентифікації (CSRF + сесія) |
| `/logout` | GET | Вихід із сесії |
| `/admin` | GET | Дашборд EasyAdmin (`ROLE_ADMIN`) |
| `/admin/book`, `/admin/author` | GET, POST | EasyAdmin CRUD для `Book` та `Author` (`ROLE_ADMIN`) |
| `/api/books` | GET | JSON-список книг із параметрами `q`, `genre`, `author_id`, `available`, `sort` |
| `/api/books/{id}` | GET | JSON однієї книги (або `404`) |
| `/api/authors` | GET | JSON-список авторів із кількістю книг |
| `/api/authors/{id}` | GET | JSON одного автора з його книгами |
| `/api/genres` | GET | JSON-список унікальних жанрів |

## Запуск автотестів (PHPUnit)

```bash
php bin/phpunit
```

## Додаткові матеріали

- [docs/LECTURE_NOTES.md](docs/LECTURE_NOTES.md) — стислий конспект основних концепцій Symfony, реалізованих у проєкті .
