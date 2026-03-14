<?php

namespace App\Controller;

use App\Entity\ColdRoom;
use App\Form\ColdRoomType;
use App\Repository\ColdRoomRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/chambres-froides')]
#[IsGranted('ROLE_CHEF_STOCK')]
class ColdRoomController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    #[Route('', name: 'app_cold_room_index', methods: ['GET'])]
    #[IsGranted('ROLE_CONTROLEUR')]
    public function index(ColdRoomRepository $repository): Response
    {
        $coldRooms = $repository->findAll();

        return $this->render('cold_room/index.html.twig', [
            'coldRooms' => $coldRooms,
        ]);
    }

    #[Route('/nouveau', name: 'app_cold_room_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $coldRoom = new ColdRoom();
        $form = $this->createForm(ColdRoomType::class, $coldRoom);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($coldRoom);
            $this->em->flush();

            $this->addFlash('success', 'Chambre froide créée avec succès.');
            return $this->redirectToRoute('app_cold_room_index');
        }

        return $this->render('cold_room/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_cold_room_show', methods: ['GET'])]
    #[IsGranted('ROLE_CONTROLEUR')]
    public function show(ColdRoom $coldRoom): Response
    {
        $racks = [];
        foreach ($coldRoom->getStockItems() as $item) {
            $rackCode = $item->getRackCode() ?? 'G1';
            if (!isset($racks[$rackCode])) {
                $racks[$rackCode] = [];
            }
            $racks[$rackCode][] = $item;
        }

        return $this->render('cold_room/show.html.twig', [
            'coldRoom' => $coldRoom,
            'racks' => $racks,
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_cold_room_edit', methods: ['GET', 'POST'])]
    public function edit(ColdRoom $coldRoom, Request $request): Response
    {
        $form = $this->createForm(ColdRoomType::class, $coldRoom);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            $this->addFlash('success', 'Chambre froide modifiée avec succès.');
            return $this->redirectToRoute('app_cold_room_index');
        }

        return $this->render('cold_room/edit.html.twig', [
            'coldRoom' => $coldRoom,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/toggle', name: 'app_cold_room_toggle', methods: ['POST'])]
    public function toggle(ColdRoom $coldRoom, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('toggle' . $coldRoom->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_cold_room_index');
        }

        // Cannot deactivate if there's stock
        if ($coldRoom->isActive() && $coldRoom->getUsedCapacity() > 0) {
            $this->addFlash('error', 'Impossible de désactiver une chambre contenant du stock.');
            return $this->redirectToRoute('app_cold_room_index');
        }

        $coldRoom->setIsActive(!$coldRoom->isActive());
        $this->em->flush();

        $status = $coldRoom->isActive() ? 'activée' : 'désactivée';
        $this->addFlash('success', "Chambre froide {$status}.");

        return $this->redirectToRoute('app_cold_room_index');
    }
}
