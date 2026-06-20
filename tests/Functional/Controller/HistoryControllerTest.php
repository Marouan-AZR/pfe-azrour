<?php

namespace App\Tests\Functional\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for HistoryController — require APP_ENV=test and a running database.
 * Run: php bin/phpunit tests/Functional/
 */
class HistoryControllerTest extends WebTestCase
{
    protected function tearDown(): void
    {
        // Symfony's Kernel registers exception handlers; restore them so PHPUnit 11 doesn't mark tests as risky
        restore_exception_handler();
        parent::tearDown();
    }

    private function createAuthenticatedClient(array $roles = ['ROLE_CHEF_STOCK']): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $email = 'functional_test_' . uniqid() . '@example.com';
        $user  = new User();
        $user->setEmail($email)
            ->setFirstName('Test')
            ->setLastName('Func')
            ->setPassword(
                static::getContainer()->get('security.password_hasher')
                    ->hashPassword($user, 'password123')
            )
            ->setRoles($roles);

        $em->persist($user);
        $em->flush();

        $client->loginUser($user);

        return $client;
    }

    public function testIndexPageLoads(): void
    {
        $client = $this->createAuthenticatedClient();
        $client->request('GET', '/historique');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('body');
    }

    public function testIndexFilterByAction(): void
    {
        $client = $this->createAuthenticatedClient();
        $client->request('GET', '/historique?action=create');

        $this->assertResponseIsSuccessful();
    }

    public function testIndexFilterByEntityType(): void
    {
        $client = $this->createAuthenticatedClient();
        $client->request('GET', '/historique?entity_type=Client');

        $this->assertResponseIsSuccessful();
    }

    public function testIndexFilterByDateRange(): void
    {
        $client = $this->createAuthenticatedClient();
        $client->request('GET', '/historique?date_from=2025-01-01&date_to=2025-12-31');

        $this->assertResponseIsSuccessful();
    }

    public function testIndexSearchFilter(): void
    {
        $client = $this->createAuthenticatedClient();
        $client->request('GET', '/historique?search=acme');

        $this->assertResponseIsSuccessful();
    }

    public function testIndexRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/historique');

        // Should redirect to login
        $this->assertResponseRedirects();
        $this->assertStringContainsString('login', $client->getResponse()->headers->get('Location') ?? '');
    }

    public function testExportCsvReturnsFile(): void
    {
        $client = $this->createAuthenticatedClient();
        $client->request('GET', '/historique/export');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString(
            'text/csv',
            $client->getResponse()->headers->get('Content-Type') ?? ''
        );
        $this->assertStringContainsString(
            'attachment',
            $client->getResponse()->headers->get('Content-Disposition') ?? ''
        );
    }

    public function testExportCsvFilenameContainsDate(): void
    {
        $client = $this->createAuthenticatedClient();
        $client->request('GET', '/historique/export');

        // StreamedResponse body is not buffered by BrowserKit; assert on header only
        $disposition = $client->getResponse()->headers->get('Content-Disposition') ?? '';
        $this->assertMatchesRegularExpression('/historique_\d{4}-\d{2}-\d{2}\.csv/', $disposition);
    }
}