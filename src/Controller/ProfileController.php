<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ProfileController extends AbstractController
{
    #[Route('', name: 'app_profile', methods: ['GET'])]
    public function show(): Response
    {
        return $this->render('profile/edit.html.twig');
    }

    #[Route('/update', name: 'app_profile_update', methods: ['POST'])]
    public function update(Request $request, EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('profile_update', $token)) {
            $this->addFlash('danger', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_profile');
        }

        $firstName = trim($request->request->get('firstName', ''));
        $lastName  = trim($request->request->get('lastName', ''));
        $email     = trim($request->request->get('email', ''));
        $phone     = trim($request->request->get('phone', '')) ?: null;

        if (!$firstName || !$lastName || !$email) {
            $this->addFlash('danger', 'Tous les champs sont obligatoires.');
            return $this->redirectToRoute('app_profile');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('danger', 'Adresse e-mail invalide.');
            return $this->redirectToRoute('app_profile');
        }

        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setEmail($email);
        $user->setPhone($phone);
        $em->flush();

        $this->addFlash('success', 'Profil mis à jour avec succès.');
        return $this->redirectToRoute('app_profile');
    }

    #[Route('/password', name: 'app_profile_password', methods: ['POST'])]
    public function changePassword(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('profile_password', $token)) {
            $this->addFlash('danger', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_profile');
        }

        $currentPassword = $request->request->get('currentPassword', '');
        $newPassword     = $request->request->get('newPassword', '');
        $confirmPassword = $request->request->get('confirmPassword', '');

        if (!$hasher->isPasswordValid($user, $currentPassword)) {
            $this->addFlash('danger', 'Mot de passe actuel incorrect.');
            return $this->redirectToRoute('app_profile');
        }

        if (strlen($newPassword) < 6) {
            $this->addFlash('danger', 'Le nouveau mot de passe doit contenir au moins 6 caractères.');
            return $this->redirectToRoute('app_profile');
        }

        if ($newPassword !== $confirmPassword) {
            $this->addFlash('danger', 'Les mots de passe ne correspondent pas.');
            return $this->redirectToRoute('app_profile');
        }

        $user->setPassword($hasher->hashPassword($user, $newPassword));
        $em->flush();

        $this->addFlash('success', 'Mot de passe modifié avec succès.');
        return $this->redirectToRoute('app_profile');
    }

    #[Route('/reset-password', name: 'app_profile_reset_password', methods: ['POST'])]
    public function sendResetLink(
        Request $request,
        EntityManagerInterface $em,
        MailerInterface $mailer
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('profile_reset_password', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_profile');
        }

        $token = bin2hex(random_bytes(32));
        $user->setResetToken($token);
        $user->setResetTokenExpiresAt(new \DateTimeImmutable('+1 hour'));
        $em->flush();

        $resetUrl = $this->generateUrl(
            'app_reset_password',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $fromAddress = $_ENV['MAILER_FROM_ADDRESS'] ?? 'noreply@goldenlogistics.ma';
        $fromName    = $_ENV['MAILER_FROM_NAME']    ?? 'Golden Logistics';

        $emailMessage = (new Email())
            ->from(new Address($fromAddress, $fromName))
            ->to($user->getEmail())
            ->subject('Réinitialisation de votre mot de passe — Golden Logistics')
            ->html($this->renderView('auth/reset_email.html.twig', [
                'user'     => $user,
                'resetUrl' => $resetUrl,
            ]));

        $emailSent = false;
        try {
            $mailer->send($emailMessage);
            $emailSent = true;
        } catch (TransportExceptionInterface) {
            // SMTP non disponible
        }

        if ($emailSent) {
            $this->addFlash('success', 'Un lien de réinitialisation a été envoyé à ' . $user->getEmail() . '. Vérifiez aussi vos spams.');
        } else {
            $this->addFlash('warning', 'Impossible d\'envoyer l\'email.');
            if ($_ENV['APP_ENV'] === 'dev') {
                $this->addFlash('dev_link', $resetUrl);
            }
        }

        return $this->redirectToRoute('app_profile');
    }
}
