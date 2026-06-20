<?php

namespace App\Tests\Functional\Controller;

use App\Entity\ColdRoom;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests fonctionnels du module Chambres Froides.
 * Vérifie les permissions par rôle et le comportement des pages CRUD.
 * Requiert APP_ENV=test et une base de données accessible.
 *
 * Règle d'ordre : loginAs() doit toujours être appelé EN PREMIER dans chaque
 * test car static::createClient() démarre le kernel — tout appel à
 * static::getContainer() avant createClient() lèverait une LogicException.
 */
class ColdRoomControllerTest extends WebTestCase
{
    protected function tearDown(): void
    {
        restore_exception_handler();
        parent::tearDown();
    }

    /** Crée un client authentifié. DOIT être appelé avant createColdRoom(). */
    private function loginAs(array $roles): KernelBrowser
    {
        $client = static::createClient();
        $em     = static::getContainer()->get(EntityManagerInterface::class);

        $user = new User();
        $user->setEmail('cr_test_' . uniqid() . '@example.com')
             ->setFirstName('Test')->setLastName('ColdRoom')
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

    /** Insère une chambre froide de test. Appeler APRÈS loginAs(). */
    private function createColdRoom(): ColdRoom
    {
        $em   = static::getContainer()->get(EntityManagerInterface::class);
        $room = new ColdRoom();
        $room->setName('Chambre Test ' . uniqid())
             ->setMaxCapacityTons('2400')
             ->setTargetTemperature('-18')
             ->setIsActive(true);

        $em->persist($room);
        $em->flush();

        return $room;
    }

    // ── Accès non authentifié ─────────────────────────────────────────────

    public function testListRedirectsToLoginWhenNotAuthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/chambres-froides');

        $this->assertResponseRedirects();
        $location = $client->getResponse()->headers->get('Location') ?? '';
        $this->assertStringContainsString('login', $location);
    }

    public function testNewPageRedirectsToLoginWhenNotAuthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/chambres-froides/nouveau');

        $this->assertResponseRedirects();
        $location = $client->getResponse()->headers->get('Location') ?? '';
        $this->assertStringContainsString('login', $location);
    }

    // ── Liste des chambres froides ─────────────────────────────────────────

    public function testControleurCanViewColdRoomList(): void
    {
        $client = $this->loginAs(['ROLE_CONTROLEUR']);
        $client->request('GET', '/chambres-froides');
        $this->assertResponseIsSuccessful();
    }

    public function testChefStockCanViewColdRoomList(): void
    {
        $client = $this->loginAs(['ROLE_CHEF_STOCK']);
        $client->request('GET', '/chambres-froides');
        $this->assertResponseIsSuccessful();
    }

    public function testColdRoomListContainsBodyElement(): void
    {
        $client = $this->loginAs(['ROLE_CHEF_STOCK']);
        $client->request('GET', '/chambres-froides');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('body');
    }

    // ── Création d'une chambre froide ──────────────────────────────────────

    public function testControleurCannotAccessNewColdRoomPage(): void
    {
        $client = $this->loginAs(['ROLE_CONTROLEUR']);
        $client->request('GET', '/chambres-froides/nouveau');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testChefStockCanAccessNewColdRoomForm(): void
    {
        $client = $this->loginAs(['ROLE_CHEF_STOCK']);
        $client->request('GET', '/chambres-froides/nouveau');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
    }

    // ── Détail d'une chambre froide ───────────────────────────────────────

    public function testColdRoomDetailPageLoadsForControleur(): void
    {
        // loginAs() EN PREMIER pour démarrer le kernel
        $client = $this->loginAs(['ROLE_CONTROLEUR']);
        $room   = $this->createColdRoom();

        $client->request('GET', '/chambres-froides/' . $room->getId());
        $this->assertResponseIsSuccessful();
    }

    public function testColdRoomDetailPageLoadsForChefStock(): void
    {
        $client = $this->loginAs(['ROLE_CHEF_STOCK']);
        $room   = $this->createColdRoom();

        $client->request('GET', '/chambres-froides/' . $room->getId());
        $this->assertResponseIsSuccessful();
    }

    // ── Modification d'une chambre froide ─────────────────────────────────

    public function testControleurCannotAccessEditPage(): void
    {
        $client = $this->loginAs(['ROLE_CONTROLEUR']);
        $room   = $this->createColdRoom();

        $client->request('GET', '/chambres-froides/' . $room->getId() . '/modifier');
        $this->assertResponseStatusCodeSame(403);
    }

    public function testChefStockCanAccessEditPage(): void
    {
        $client = $this->loginAs(['ROLE_CHEF_STOCK']);
        $room   = $this->createColdRoom();

        $client->request('GET', '/chambres-froides/' . $room->getId() . '/modifier');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
    }

    // ── Page inexistante ──────────────────────────────────────────────────

    public function testNonExistentColdRoomReturns404(): void
    {
        $client = $this->loginAs(['ROLE_CHEF_STOCK']);
        $client->request('GET', '/chambres-froides/99999');
        $this->assertResponseStatusCodeSame(404);
    }
}
