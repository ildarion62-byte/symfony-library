<?php

namespace App\Controller\Admin;

use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly BookRepository $bookRepository,
        private readonly AuthorRepository $authorRepository,
    ) {
    }

    public function index(): Response
    {
        $totalCopies = (int) $this->bookRepository->createQueryBuilder('b')
            ->select('COALESCE(SUM(b.availableCopies), 0)')
            ->getQuery()
            ->getSingleScalarResult();

        return $this->render('admin/dashboard.html.twig', [
            'bookCount' => $this->bookRepository->count(),
            'authorCount' => $this->authorRepository->count(),
            'totalCopies' => $totalCopies,
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Книжкова бібліотека — Адмін')
            ->renderContentMaximized();
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Головна', 'fa fa-home');
        yield MenuItem::section('Каталог');
        yield MenuItem::linkTo(BookCrudController::class, 'Книги', 'fa fa-book');
        yield MenuItem::linkTo(AuthorCrudController::class, 'Автори', 'fa fa-user-pen');
        yield MenuItem::section('Навігація');
        yield MenuItem::linkToRoute('Публічний сайт', 'fa fa-globe', 'app_home');
        yield MenuItem::linkToLogout('Вийти', 'fa fa-right-from-bracket');
    }
}
