<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Crée un utilisateur administrateur'
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $io->ask('Email', 'admin@example.com');
        $firstName = $io->ask('Prénom', 'Admin');
        $lastName = $io->ask('Nom', 'System');
        $password = $io->askHidden('Mot de passe') ?: 'admin123';
        
        $role = $io->choice('Rôle', [
            'ROLE_DIRECTEUR' => 'Directeur (accès total)',
            'ROLE_CHEF_STOCK' => 'Chef de stock (entrées/sorties)',
            'ROLE_MAGASINIER' => 'Magasinier (consultation)',
            'ROLE_CLIENT' => 'Client',
        ], 'ROLE_DIRECTEUR');

        $user = new User();
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setRoles([$role]);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->em->persist($user);
        $this->em->flush();

        $io->success("Administrateur créé : $email");

        return Command::SUCCESS;
    }
}
