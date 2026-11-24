<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Створює користувача з роллю ROLE_ADMIN (пароль вводиться інтерактивно або передається опцією і не зберігається в репозиторії).',
)]
final class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::OPTIONAL, 'Email адміністратора')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Пароль адміністратора (якщо не вказано — запитується інтерактивно)')
            ->addOption('update', 'u', InputOption::VALUE_NONE, 'Оновити пароль і роль, якщо користувач уже існує');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getArgument('email');
        if (!is_string($email) || trim($email) === '') {
            $email = (string) $io->ask('Введіть email адміністратора');
        }
        $email = mb_strtolower(trim($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('Некоректний формат email.');

            return Command::FAILURE;
        }

        $existingUser = $this->userRepository->findOneBy(['email' => $email]);
        $updateExisting = (bool) $input->getOption('update');

        if ($existingUser !== null && !$updateExisting) {
            $io->error(sprintf(
                'Користувач "%s" вже існує. Використайте прапорець --update, щоб оновити пароль та права ROLE_ADMIN.',
                $email,
            ));

            return Command::FAILURE;
        }

        $plainPassword = $input->getOption('password');
        if (!is_string($plainPassword) || $plainPassword === '') {
            $plainPassword = (string) $io->askHidden('Введіть пароль адміністратора (мінімум 8 символів)');
            $confirmPassword = (string) $io->askHidden('Повторіть пароль');

            if ($plainPassword !== $confirmPassword) {
                $io->error('Паролі не співпадають.');

                return Command::FAILURE;
            }
        }

        if (mb_strlen($plainPassword) < 8) {
            $io->error('Пароль має містити щонайменше 8 символів.');

            return Command::FAILURE;
        }

        $user = $existingUser ?? new User();
        $user->setEmail($email);

        $roles = $user->getRoles();
        $roles[] = 'ROLE_ADMIN';
        $user->setRoles($roles);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf(
            $existingUser !== null
                ? 'Дані адміністратора "%s" оновлено (ROLE_ADMIN).'
                : 'Адміністратора "%s" успішно створено (ROLE_ADMIN).',
            $email,
        ));

        return Command::SUCCESS;
    }
}
