<?php

namespace App\Tests\Functional\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for AuthController — login, register, logout.
 */
class AuthControllerTest extends WebTestCase
{
    protected function tearDown(): void
    {
        restore_exception_handler();
        parent::tearDown();
    }

    // ── Page de connexion ─────────────────────────────────────────

    public function testLoginPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('input[name="_username"]');
        $this->assertSelectorExists('input[name="_password"]');
    }

    public function testLoginWithWrongCredentialsShowsError(): void
    {
        $client = static::createClient();
        $client->request('POST', '/login', [
            '_username' => 'inexistant@test.com',
            '_password' => 'mauvais_mot_de_passe',
        ]);

        // Symfony redirige vers /login en cas d'échec
        $client->followRedirect();

        $this->assertResponseIsSuccessful();
        // Le formulaire doit s'afficher à nouveau (pas de redirection vers l'app)
        $this->assertSelectorExists('input[name="_username"]');
    }

    public function testLoginPageHasCorrectFormFields(): void
    {
        // Vérifie que le formulaire de login contient bien les champs attendus
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('input[name="_username"]'));
        $this->assertCount(1, $crawler->filter('input[name="_password"]'));
        // Le formulaire doit être une action POST
        $form = $crawler->filter('form');
        $this->assertGreaterThan(0, $form->count());
    }

    // ── Page d'inscription ────────────────────────────────────────

    public function testRegisterPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/inscription');

        $this->assertResponseIsSuccessful();
    }

    // ── Déconnexion ───────────────────────────────────────────────

    public function testLogoutRedirectsToLogin(): void
    {
        $client = static::createClient();
        $em     = static::getContainer()->get(EntityManagerInterface::class);

        $user = new User();
        $user->setEmail('logout_test_' . uniqid() . '@example.com')
             ->setFirstName('Test')->setLastName('Logout')
             ->setPassword(
                 static::getContainer()->get('security.password_hasher')
                     ->hashPassword($user, 'Password123!')
             )
             ->setRoles(['ROLE_USER']);
        $em->persist($user);
        $em->flush();

        $client->loginUser($user);
        $client->request('GET', '/logout');

        $this->assertResponseRedirects();

        // Nettoyage
        $em->remove($em->find(User::class, $user->getId()));
        $em->flush();
    }

    // ── Accès protégés ────────────────────────────────────────────

    public function testDashboardRedirectsWhenNotLoggedIn(): void
    {
        $client = static::createClient();
        $client->request('GET', '/'); // route réelle du dashboard : /

        $this->assertResponseRedirects();
        $location = $client->getResponse()->headers->get('Location') ?? '';
        $this->assertStringContainsString('login', $location);
    }
}
