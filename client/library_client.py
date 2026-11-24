#!/usr/bin/env python3
"""
Консольний клієнт для веб-сервісу «Книжкова бібліотека» (Symfony).

Можливості:
1. Читання даних із JSON API (/api/books, /api/books/{id}, /api/authors, /api/authors/{id})
   з підтримкою пошуку та фільтрації (QueryBuilder на бекенді).
2. Демонстрація входу в адмін-панель EasyAdmin через сесію (requests.Session + CSRF-токен).
"""

from __future__ import annotations

import argparse
import getpass
import os
import re
import sys
from html.parser import HTMLParser
from typing import Any

import requests


def configure_utf8_io() -> None:
    """Забезпечує коректне виведення кирилиці у Windows-консолі."""
    if hasattr(sys.stdout, "reconfigure"):
        sys.stdout.reconfigure(encoding="utf-8")
    if hasattr(sys.stderr, "reconfigure"):
        sys.stderr.reconfigure(encoding="utf-8")


class LoginFormParser(HTMLParser):
    """Простий HTML-парсер для витягування _csrf_token та <title>."""

    def __init__(self) -> None:
        super().__init__()
        self.csrf_token: str | None = None
        self.title: str = ""
        self._in_title = False

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        if tag.lower() == "input":
            attr_map = {k.lower(): (v or "") for k, v in attrs}
            if attr_map.get("name") == "_csrf_token":
                self.csrf_token = attr_map.get("value")
        elif tag.lower() == "title":
            self._in_title = True

    def handle_endtag(self, tag: str) -> None:
        if tag.lower() == "title":
            self._in_title = False

    def handle_data(self, data: str) -> None:
        if self._in_title:
            self.title += data


def extract_title(html: str) -> str:
    match = re.search(r"<title[^>]*>(.*?)</title>", html, flags=re.IGNORECASE | re.DOTALL)
    if not match:
        return "(без заголовка)"
    return " ".join(match.group(1).split())


def api_get(base_url: str, path: str, params: dict[str, Any] | None = None) -> dict[str, Any]:
    url = f"{base_url.rstrip('/')}{path}"
    try:
        response = requests.get(url, params=params, timeout=10)
    except requests.RequestException as exc:
        print(f"[ПОМИЛКА] Не вдалося підключитися до {url}: {exc}", file=sys.stderr)
        sys.exit(1)

    if response.status_code == 404:
        try:
            err = response.json().get("error", "Запис не знайдено.")
        except ValueError:
            err = "Запис не знайдено (404)."
        print(f"[404] {err}", file=sys.stderr)
        sys.exit(1)

    try:
        response.raise_for_status()
        return response.json()
    except (requests.HTTPError, ValueError) as exc:
        print(f"[ПОМИЛКА] Некоректна відповідь сервера ({response.status_code}): {exc}", file=sys.stderr)
        sys.exit(1)


def print_books_table(items: list[dict[str, Any]]) -> None:
    if not items:
        print("Книг за заданими параметрами не знайдено.")
        return

    header = f"{'ID':>3} | {'Назва':<32} | {'Автор':<22} | {'Жанр':<20} | {'Рік':>4} | {'Прим.':>5}"
    print(header)
    print("-" * len(header))
    for book in items:
        author_name = (book.get("author") or {}).get("fullName", "—")
        title = str(book.get("title", ""))[:32]
        author_short = str(author_name)[:22]
        genre = str(book.get("genre", ""))[:20]
        copies = book.get("availableCopies", 0)
        print(
            f"{book.get('id', ''):>3} | {title:<32} | {author_short:<22} | "
            f"{genre:<20} | {book.get('publishedYear', ''):>4} | {copies:>5}"
        )


def cmd_books(args: argparse.Namespace) -> None:
    params: dict[str, Any] = {}
    if args.q:
        params["q"] = args.q
    if args.genre:
        params["genre"] = args.genre
    if args.author_id:
        params["author_id"] = args.author_id
    if args.available:
        params["available"] = "1"
    if args.sort:
        params["sort"] = args.sort

    data = api_get(args.base_url, "/api/books", params=params)
    print(f"\n=== Каталог книг (знайдено: {data.get('count', 0)}) ===\n")
    print_books_table(data.get("items", []))


def cmd_book_detail(args: argparse.Namespace) -> None:
    book = api_get(args.base_url, f"/api/books/{args.id}")
    author = book.get("author") or {}
    status = f"В наявності ({book.get('availableCopies', 0)} прим.)" if book.get("available") else "Немає в наявності"

    print(f"\n=== Книга #{book.get('id')}: {book.get('title')} ===")
    print(f"Автор:      {author.get('fullName', '—')} ({author.get('country', '—')})")
    print(f"Жанр:       {book.get('genre')}")
    print(f"ISBN:       {book.get('isbn')}")
    print(f"Рік:        {book.get('publishedYear')}")
    print(f"Сторінок:   {book.get('pages')}")
    print(f"Статус:     {status}")
    if book.get("description"):
        print(f"Анотація:   {book['description']}")


def cmd_authors(args: argparse.Namespace) -> None:
    data = api_get(args.base_url, "/api/authors")
    items = data.get("items", [])
    print(f"\n=== Автори бібліотеки (всього: {data.get('count', 0)}) ===\n")
    header = f"{'ID':>3} | {'Повне ім’я':<26} | {'Країна':<18} | {'Нар.':>4} | {'Книг':>4}"
    print(header)
    print("-" * len(header))
    for author in items:
        birth = author.get("birthYear") or "—"
        print(
            f"{author.get('id', ''):>3} | {str(author.get('fullName', ''))[:26]:<26} | "
            f"{str(author.get('country', ''))[:18]:<18} | {str(birth):>4} | {author.get('bookCount', 0):>4}"
        )


def cmd_author_detail(args: argparse.Namespace) -> None:
    author = api_get(args.base_url, f"/api/authors/{args.id}")
    print(f"\n=== Автор #{author.get('id')}: {author.get('fullName')} ===")
    print(f"Країна:     {author.get('country')}")
    if author.get("birthYear"):
        print(f"Рік нар.:   {author['birthYear']}")
    if author.get("biography"):
        print(f"Біографія:  {author['biography']}")

    books = author.get("books", [])
    print(f"\nКниги автора ({len(books)}):")
    for book in books:
        print(
            f"  - [#{book['id']}] {book['title']} ({book['publishedYear']}, "
            f"{book['genre']}, прим.: {book['availableCopies']})"
        )


def cmd_admin_demo(args: argparse.Namespace) -> None:
    """Демонстрація входу в захищену адмінку EasyAdmin через HTTP-сесію та CSRF."""
    base = args.base_url.rstrip("/")
    email = args.email or input("Email адміністратора: ").strip()
    password = args.password or getpass.getpass("Пароль адміністратора: ")

    if not email or not password:
        print("[ПОМИЛКА] Email та пароль обов’язкові.", file=sys.stderr)
        sys.exit(1)

    session = requests.Session()

    print("\n1) Перевірка доступу до /admin БЕЗ авторизації...")
    unauth_resp = session.get(f"{base}/admin", allow_redirects=False, timeout=10)
    print(f"   HTTP {unauth_resp.status_code} -> Location: {unauth_resp.headers.get('Location', '—')}")

    print("\n2) Завантаження форми входу GET /login та зчитування CSRF-токена...")
    login_page = session.get(f"{base}/login", timeout=10)
    login_page.raise_for_status()

    parser = LoginFormParser()
    parser.feed(login_page.text)
    if not parser.csrf_token:
        print("[ПОМИЛКА] Не вдалося знайти _csrf_token на сторінці /login.", file=sys.stderr)
        sys.exit(1)
    print(f"   Отримано _csrf_token: {parser.csrf_token[:16]}... (сесійні cookies: {list(session.cookies.keys())})")

    print("\n3) Надсилання POST /login через requests.Session()...")
    login_resp = session.post(
        f"{base}/login",
        data={
            "_username": email,
            "_password": password,
            "_csrf_token": parser.csrf_token,
        },
        allow_redirects=True,
        timeout=10,
    )

    if "/login" in login_resp.url and login_resp.status_code == 200:
        print("[ПОМИЛКА] Вхід не вдався: перевірте email, пароль або наявність ROLE_ADMIN.", file=sys.stderr)
        sys.exit(1)

    print(f"   Успішно! Поточний URL: {login_resp.url} (HTTP {login_resp.status_code})")
    print(f"   Заголовок сторінки: {extract_title(login_resp.text)}")

    print("\n4) Доступ до захищеного CRUD-розділу GET /admin/book за тією ж сесією...")
    crud_resp = session.get(f"{base}/admin/book", allow_redirects=False, timeout=10)
    print(f"   HTTP {crud_resp.status_code} | Title: {extract_title(crud_resp.text)}")

    if crud_resp.status_code != 200:
        print("[ПОМИЛКА] Не вдалося відкрити CRUD-список книг в EasyAdmin.", file=sys.stderr)
        sys.exit(1)

    print("\n5) Вихід із сесії GET /logout і повторна перевірка /admin...")
    session.get(f"{base}/logout", allow_redirects=True, timeout=10)
    after_logout = session.get(f"{base}/admin", allow_redirects=False, timeout=10)
    print(f"   Після виходу GET /admin -> HTTP {after_logout.status_code} (Location: {after_logout.headers.get('Location', '—')})")
    print("\n[OK] Демонстрацію сесійної автентифікації успішно завершено.")


def interactive_menu(base_url: str) -> None:
    while True:
        print("\n========================================")
        print(" Книжкова бібліотека — Python CLI клієнт")
        print("========================================")
        print("1. Показати всі книги (/api/books)")
        print("2. Пошук / фільтр книг (QueryBuilder)")
        print("3. Деталі книги за ID (/api/books/{id})")
        print("4. Показати всіх авторів (/api/authors)")
        print("5. Деталі автора за ID (/api/authors/{id})")
        print("6. Демо входу в адмінку EasyAdmin через сесію")
        print("0. Вихід")

        choice = input("\nОберіть пункт: ").strip()
        if choice == "0":
            break
        elif choice == "1":
            cmd_books(argparse.Namespace(base_url=base_url, q=None, genre=None, author_id=None, available=False, sort="title"))
        elif choice == "2":
            q = input("Пошуковий запит (Enter — пропустити): ").strip() or None
            genre = input("Жанр (Enter — пропустити): ").strip() or None
            only_av = input("Лише в наявності? (y/N): ").strip().lower() == "y"
            cmd_books(argparse.Namespace(base_url=base_url, q=q, genre=genre, author_id=None, available=only_av, sort="title"))
        elif choice == "3":
            book_id = input("ID книги: ").strip()
            if book_id.isdigit():
                cmd_book_detail(argparse.Namespace(base_url=base_url, id=int(book_id)))
            else:
                print("Введіть числовий ID.")
        elif choice == "4":
            cmd_authors(argparse.Namespace(base_url=base_url))
        elif choice == "5":
            author_id = input("ID автора: ").strip()
            if author_id.isdigit():
                cmd_author_detail(argparse.Namespace(base_url=base_url, id=int(author_id)))
            else:
                print("Введіть числовий ID.")
        elif choice == "6":
            cmd_admin_demo(argparse.Namespace(base_url=base_url, email=None, password=None))
        else:
            print("Невідомий пункт меню.")


def build_parser() -> argparse.ArgumentParser:
    default_url = os.environ.get("LIBRARY_BASE_URL", "http://127.0.0.1:8000")
    parser = argparse.ArgumentParser(
        description="Консольний Python-клієнт для Symfony-сервісу «Книжкова бібліотека».",
    )
    parser.add_argument(
        "--base-url",
        default=default_url,
        help=f"Базова адреса сервера Symfony (за замовчуванням: {default_url})",
    )

    sub = parser.add_subparsers(dest="command")

    p_books = sub.add_parser("books", help="Отримати список книг із JSON API")
    p_books.add_argument("--q", help="Пошук за назвою, автором, ISBN або жанром")
    p_books.add_argument("--genre", help="Фільтр за точним жанром")
    p_books.add_argument("--author-id", type=int, help="Фільтр за ID автора")
    p_books.add_argument("--available", action="store_true", help="Лише доступні книги")
    p_books.add_argument(
        "--sort",
        default="title",
        choices=["title", "author", "year_desc", "year_asc", "pages_desc"],
        help="Сортування",
    )
    p_books.set_defaults(func=cmd_books)

    p_book = sub.add_parser("book", help="Показати одну книгу за ID")
    p_book.add_argument("id", type=int, help="ID книги")
    p_book.set_defaults(func=cmd_book_detail)

    p_authors = sub.add_parser("authors", help="Показати список авторів")
    p_authors.set_defaults(func=cmd_authors)

    p_author = sub.add_parser("author", help="Показати одного автора та його книги")
    p_author.add_argument("id", type=int, help="ID автора")
    p_author.set_defaults(func=cmd_author_detail)

    p_admin = sub.add_parser("admin-demo", help="Увійти в адмінку EasyAdmin через сесію (бонус)")
    p_admin.add_argument("--email", help="Email адміністратора (якщо не вказано — запитується)")
    p_admin.add_argument("--password", help="Пароль (краще не передавати в CLI, щоб запитало приховано)")
    p_admin.set_defaults(func=cmd_admin_demo)

    return parser


def main() -> None:
    configure_utf8_io()
    parser = build_parser()
    args = parser.parse_args()

    if args.command is None:
        interactive_menu(args.base_url)
    else:
        args.func(args)


if __name__ == "__main__":
    main()
