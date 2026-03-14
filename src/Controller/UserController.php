<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/utilisateurs')]
#[IsGranted('ROLE_CHEF_STOCK')]
class UserController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    #[Route('', name: 'app_user_index', methods: ['GET'])]
    public function index(UserRepository $repository): Response
    {
        $users = $repository->findBy([], ['createdAt' => 'DESC']);
        $pendingUsers = $repository->findBy(['isActive' => false], ['createdAt' => 'DESC']);
        $clients = $this->em->getRepository(Client::class)->findBy(['isActive' => true], ['companyName' => 'ASC']);

        return $this->render('user/index.html.twig', [
            'users' => $users,
            'pendingUsers' => $pendingUsers,
            'clients' => $clients,
        ]);
    }

    #[Route('/{id}/activer', name: 'app_user_activate', methods: ['POST'])]
    public function activate(User $user, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('activate' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_user_index');
        }

        $role = $request->request->get('role');
        if (!$role) {
            $this->addFlash('error', 'Veuillez sélectionner un rôle.');
            return $this->redirectToRoute('app_user_index');
        }

        // Validate role
        $validRoles = array_map(fn($r) => $r->value, UserRole::cases());
        if (!in_array($role, $validRoles)) {
            $this->addFlash('error', 'Rôle invalide.');
            return $this->redirectToRoute('app_user_index');
        }

        // If client role, check if client is selected
        if ($role === UserRole::CLIENT->value) {
            $clientId = $request->request->get('client_id');
            if ($clientId) {
                $client = $this->em->getRepository(Client::class)->find($clientId);
                if ($client) {
                    $user->setClient($client);
                }
            }
        }

        $user->setRoles([$role]);
        $user->setIsActive(true);
        $this->em->flush();

        $this->addFlash('success', sprintf('Utilisateur %s activé avec le rôle %s.', $user->getFullName(), UserRole::from($role)->label()));
        return $this->redirectToRoute('app_user_index');
    }

    #[Route('/{id}/desactiver', name: 'app_user_deactivate', methods: ['POST'])]
    public function deactivate(User $user, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('deactivate' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_user_index');
        }

        // Cannot deactivate yourself
        if ($user === $this->getUser()) {
            $this->addFlash('error', 'Vous ne pouvez pas désactiver votre propre compte.');
            return $this->redirectToRoute('app_user_index');
        }

        $user->setIsActive(false);
        $this->em->flush();

        $this->addFlash('success', sprintf('Utilisateur %s désactivé.', $user->getFullName()));
        return $this->redirectToRoute('app_user_index');
    }

    #[Route('/{id}/role', name: 'app_user_change_role', methods: ['POST'])]
    public function changeRole(User $user, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('role' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_user_index');
        }

        $role = $request->request->get('role');
        $validRoles = array_map(fn($r) => $r->value, UserRole::cases());
        
        if (!in_array($role, $validRoles)) {
            $this->addFlash('error', 'Rôle invalide.');
            return $this->redirectToRoute('app_user_index');
        }

        $user->setRoles([$role]);
        $this->em->flush();

        $this->addFlash('success', sprintf('Rôle de %s modifié en %s.', $user->getFullName(), UserRole::from($role)->label()));
        return $this->redirectToRoute('app_user_index');
    }
}
