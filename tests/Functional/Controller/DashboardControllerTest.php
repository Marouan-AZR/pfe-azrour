<?php

namespace App\Tests\Functional\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests fonctionnels du tableau de bord.
 * Vérifie le routage conditionnel selon le rôle de l'utilisateur connecté.
 * Requiert APP_ENV=test et une base de données accessible.
 */
class DashboardControllerTest extends WebTestCase
{
    protected function tearDown(): void
    {
        restore_exception_handler();
        parent::tearDown();
    }

    private function loginAs(array $roles): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        $client = static::createClient();
        $em     = static::getContainer()->get(EntityManagerInterface::class);

        $user = new User();
        $user->setEmail('dash_test_' . uniqid() . '@example.com')
             ->setFirstName('Test')->setLastName('Dashboard')
             ->setPassword(
                 static::getContainer()->get('security.password_hasher')
                     ->hashPassword($user, 'Password1!')
             )
             ->setRoles($roles);

        $em->persist($user);
        $em->flush();

        $client->loginUser($user);

        return $client;
    }

    // ── Accès non authentifié ─────────────────────────────────────────────

    public function testDashboardRedirectsToLoginWhenNotAuthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertResponseRedirects();
        $location = $client->getResponse()->headers->get('Location') ?? '';
        $this->assertStringContainsString('login', $location);
    }

    // ── Chef de stock ─────────────────────────────────────────────────────

    public function testChefStockCanAccessDashboard(): void
    {
        $client = $this->loginAs(['ROLE_CHEF_STOCK']);
        $client->request('GET', '/');
        $this->assertResponseIsSuccessful();
    }

    public function testChefStockCanAccessColdRooms(): void
    {
        $client = $this->loginAs(['ROLE_CHEF_STOCK']);
        $client->request('GET', '/chambres-froides');
        $this->assertResponseIsSuccessful();
    }

    // ── Contrôleur ───────────────────────────────────────────────────────

    public function testControleurCanAccessDashboard(): void
    {
        $client = $this->loginAs(['ROLE_CONTROLEUR']);
        $client->request('GET', '/');
        $this->assertResponseIsSuccessful();
    }

    public function testControleurCannotAccessReports(): void
    {
        $client = $this->loginAs(['ROLE_CONTROLEUR']);
        $client->request('GET', '/rapports');

        // Doit être refusé (403) car la route requiert ROLE_DIRECTEUR
        $this->assertTrue(
            $client->getResponse()->getStatusCode() === 403
            || $client->getResponse()->isRedirect()
        );
    }

    // ── Directeur ─────────────────────────────────────────────────────────

    public function testDirecteurCanAccessDashboard(): void
    {
        $client = $this->loginAs(['ROLE_DIRECTEUR']);
        $client->request('GET', '/');
        $this->assertResponseIsSuccessful();
    }

    public function testDirecteurCanAccessReports(): void
    {
        $client = $this->loginAs(['ROLE_DIRECTEUR']);
        $client->request('GET', '/rapports');
        $this->assertResponseIsSuccessful();
    }

    // ── Alertes ───────────────────────────────────────────────────────────

    public function testAlertsPageRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/alertes');

        $this->assertResponseRedirects();
        $location = $client->getResponse()->headers->get('Location') ?? '';
        $this->assertStringContainsString('login', $location);
    }

    public function testChefStockCanAccessAlerts(): void
    {
        $client = $this->loginAs(['ROLE_CHEF_STOCK']);
        $client->request('GET', '/alertes');
        $this->assertResponseIsSuccessful();
    }

    // ── Historique ────────────────────────────────────────────────────────

    public function testHistoryPageRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/historique');

        $this->assertResponseRedirects();
        $location = $client->getResponse()->headers->get('Location') ?? '';
        $this->assertStringContainsString('login', $location);
    }

    // ── Pages publiques accessibles sans connexion ────────────────────────

    public function testPublicHomePageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/accueil');
        $this->assertResponseIsSuccessful();
    }

    public function testPublicAboutPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/a-propos');
        $this->assertResponseIsSuccessful();
    }
}
