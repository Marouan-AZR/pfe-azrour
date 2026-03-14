<?php

namespace App\Command;

use App\Entity\Client;
use App\Entity\ColdRoom;
use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-test-users',
    description: 'Crée des utilisateurs de test pour chaque rôle'
)]
class CreateTestUsersCommand extends Command
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

        // Create test client first
        $client = $this->em->getRepository(Client::class)->findOneBy(['email' => 'client@test.com']);
        if (!$client) {
            $client = new Client();
            $client->setCompanyName('Société Test SARL');
            $client->setEmail('client@test.com');
            $client->setPhone('+212 600 000 000');
            $client->setAddress("123 Rue Test\nDakhla, Maroc");
            $this->em->persist($client);
            $io->success('Client de test créé: Société Test SARL');
        }

        // Create cold rooms if not exist
        $coldRoomNames = ['Chambre 1', 'Chambre 2', 'Chambre 3', 'Chambre 4', 'Chambre 5'];
        foreach ($coldRoomNames as $index => $name) {
            $existing = $this->em->getRepository(ColdRoom::class)->findOneBy(['name' => $name]);
            if (!$existing) {
                $coldRoom = new ColdRoom();
                $coldRoom->setName($name);
                $coldRoom->setMaxCapacityTons('2000');
                $coldRoom->setTargetTemperature('-25');
                $coldRoom->setIsActive(true);
                $this->em->persist($coldRoom);
                $io->info("Chambre froide créée: $name");
            }
        }

        // Users to create
        $users = [
            [
                'email' => 'chef@golden-logistics.ma',
                'firstName' => 'Ahmed',
                'lastName' => 'Benali',
                'roles' => [UserRole::CHEF_STOCK->value],
                'description' => 'Chef de Stock'
            ],
            [
                'email' => 'controleur@golden-logistics.ma',
                'firstName' => 'Youssef',
                'lastName' => 'Mansouri',
                'roles' => [UserRole::CONTROLEUR->value],
                'description' => 'Contrôleur'
            ],
            [
                'email' => 'directeur@golden-logistics.ma',
                'firstName' => 'Karim',
                'lastName' => 'Alaoui',
                'roles' => [UserRole::DIRECTEUR->value],
                'description' => 'Directeur'
            ],
            [
                'email' => 'patron@golden-logistics.ma',
                'firstName' => 'Hassan',
                'lastName' => 'El Fassi',
                'roles' => [UserRole::PATRON->value],
                'description' => 'Patron'
            ],
            [
                'email' => 'client@golden-logistics.ma',
                'firstName' => 'Omar',
                'lastName' => 'Tazi',
                'roles' => [UserRole::CLIENT->value],
                'description' => 'Client',
                'client' => $client
            ],
        ];

        $io->title('Création des utilisateurs de test');
        $io->text('Mot de passe pour tous: password123');
        $io->newLine();

        foreach ($users as $userData) {
            $existingUser = $this->em->getRepository(User::class)->findOneBy(['email' => $userData['email']]);
            
            if ($existingUser) {
                $io->warning("Utilisateur existe déjà: {$userData['email']}");
                continue;
            }

            $user = new User();
            $user->setEmail($userData['email']);
            $user->setFirstName($userData['firstName']);
            $user->setLastName($userData['lastName']);
            $user->setRoles($userData['roles']);
            $user->setIsActive(true);
            $user->setPassword($this->passwordHasher->hashPassword($user, 'password123'));
            
            if (isset($userData['client'])) {
                $user->setClient($userData['client']);
            }

            $this->em->persist($user);
            $io->success("✓ {$userData['description']}: {$userData['email']}");
        }

        $this->em->flush();

        $io->newLine();
        $io->section('Récapitulatif des comptes créés');
        $io->table(
            ['Rôle', 'Email', 'Mot de passe'],
            [
                ['Chef de Stock', 'chef@golden-logistics.ma', 'password123'],
                ['Contrôleur', 'controleur@golden-logistics.ma', 'password123'],
                ['Directeur', 'directeur@golden-logistics.ma', 'password123'],
                ['Patron', 'patron@golden-logistics.ma', 'password123'],
                ['Client', 'client@golden-logistics.ma', 'password123'],
            ]
        );

        $io->success('Tous les utilisateurs de test ont été créés!');

        return Command::SUCCESS;
    }
}
