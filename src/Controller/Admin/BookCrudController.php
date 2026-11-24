<?php

namespace App\Controller\Admin;

use App\Entity\Book;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class BookCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Book::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Книга')
            ->setEntityLabelInPlural('Книги')
            ->setDefaultSort(['title' => 'ASC'])
            ->setSearchFields(['title', 'isbn', 'genre', 'author.fullName']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('author', 'Автор'))
            ->add(TextFilter::new('genre', 'Жанр'))
            ->add(NumericFilter::new('publishedYear', 'Рік видання'))
            ->add(NumericFilter::new('availableCopies', 'Примірників'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('title', 'Назва');
        yield AssociationField::new('author', 'Автор');
        yield TextField::new('isbn', 'ISBN');
        yield TextField::new('genre', 'Жанр');
        yield IntegerField::new('publishedYear', 'Рік видання');
        yield IntegerField::new('pages', 'Сторінок');
        yield IntegerField::new('availableCopies', 'Доступно примірників');
        yield TextareaField::new('description', 'Анотація')->hideOnIndex();
    }
}
