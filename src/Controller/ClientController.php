<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\User;
use App\Enum\UserRole;
use App\Form\ClientType;
use App\Repository\ClientRepository;
use App\Security\Voter\ClientVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/clients')]
class ClientController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    #[Route('', name: 'app_client_index', methods: ['GET'])]
    #[IsGranted(ClientVoter::VIEW)]
    public function index(Request $request, ClientRepository $repository): Response
    {
        $search = $request->query->get('q', '');
        
        if ($search) {
            $clients = $repository->search($search);
        } else {
            $clients = $repository->findBy([], ['companyName' => 'ASC']);
        }

        return $this->render('client/index.html.twig', [
            'clients' => $clients,
            'search' => $search,
        ]);
    }

    #[Route('/nouveau', name: 'app_client_new', methods: ['GET', 'POST'])]
    #[IsGranted(ClientVoter::CREATE)]
    public function new(Request $request): Response
    {
        $client = new Client();
        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($client);
            $this->em->flush();

            $this->addFlash('success', 'Client créé avec succès. Code: ' . $client->getCode());
            return $this->redirectToRoute('app_client_index');
        }

        return $this->render('client/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_client_edit', methods: ['GET', 'POST'])]
    #[IsGranted(ClientVoter::EDIT)]
    public function edit(Client $client, Request $request): Response
    {
        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            $this->addFlash('success', 'Client modifié avec succès.');
            return $this->redirectToRoute('app_client_index');
        }

        return $this->render('client/edit.html.twig', [
            'client' => $client,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/toggle', name: 'app_client_toggle', methods: ['POST'])]
    #[IsGranted(ClientVoter::EDIT)]
    public function toggle(Client $client, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('toggle' . $client->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_client_index');
        }

        $client->setIsActive(!$client->isActive());
        $this->em->flush();

        $status = $client->isActive() ? 'activé' : 'désactivé';
        $this->addFlash('success', "Client {$status}.");

        return $this->redirectToRoute('app_client_index');
    }

    #[Route('/{id}/supprimer', name: 'app_client_delete', methods: ['POST'])]
    #[IsGranted(ClientVoter::DELETE)]
    public function delete(Client $client, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete' . $client->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_client_index');
        }

        // Check if client has stock or invoices
        if ($client->getStockItems()->count() > 0) {
            $this->addFlash('error', 'Impossible de supprimer ce client car il possède du stock.');
            return $this->redirectToRoute('app_client_index');
        }

        $this->em->remove($client);
        $this->em->flush();

        $this->addFlash('success', 'Client supprimé.');

        return $this->redirectToRoute('app_client_index');
    }

    #[Route('/{id}/creer-compte', name: 'app_client_create_account', methods: ['POST'])]
    #[IsGranted(ClientVoter::EDIT)]
    public function createAccount(Client $client, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('create_account' . $client->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_client_index');
        }

        // Check if user already exists for this client
        $existingUser = $this->em->getRepository(User::class)->findOneBy(['client' => $client]);
        if ($existingUser) {
            $this->addFlash('warning', 'Un compte utilisateur existe déjà pour ce client.');
            return $this->redirectToRoute('app_client_index');
        }

        // Create user account
        $user = new User();
        $user->setEmail($client->getEmail());
        $user->setFirstName($client->getCompanyName());
        $user->setLastName('(Client)');
        $user->setRoles([UserRole::CLIENT->value]);
        $user->setClient($client);
        
        // Generate random password
        $password = bin2hex(random_bytes(8));
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->em->persist($user);
        $this->em->flush();

        $this->addFlash('success', sprintf(
            'Compte créé pour %s. Mot de passe temporaire: %s (à communiquer au client)',
            $client->getCompanyName(),
            $password
        ));

        return $this->redirectToRoute('app_client_index');
    }
}
