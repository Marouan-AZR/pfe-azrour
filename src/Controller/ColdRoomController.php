<?php

namespace App\Controller;

use App\Entity\ColdRoom;
use App\Form\ColdRoomType;
use App\Repository\ColdRoomRepository;
use App\Repository\PaletteRepository;
use App\Service\ColdRoomOccupancyService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/chambres-froides')]
class ColdRoomController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ColdRoomOccupancyService $occupancyService,
    ) {}

    #[Route('', name: 'app_cold_room_index', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(ColdRoomRepository $repository): Response
    {
        $coldRooms = $repository->findAll();
        $occupancyStats = $this->occupancyService->getOccupancyStatsForRooms($coldRooms);

        return $this->render('cold_room/index.html.twig', [
            'coldRooms' => $coldRooms,
            'occupancyStats' => $occupancyStats,
        ]);
    }

    #[Route('/nouveau', name: 'app_cold_room_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_CHEF_STOCK')]
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
    #[IsGranted('ROLE_USER')]
    public function show(ColdRoom $coldRoom, PaletteRepository $paletteRepository): Response
    {
        $occupancyStats = $this->occupancyService->getOccupancyStatsForRooms([$coldRoom]);

        $racks      = [];
        $otherRacks = [];
        foreach ($paletteRepository->findActivePalettesForColdRoom($coldRoom) as $palette) {
            $rayon = $palette->getRayon();
            if ($rayon === null) {
                continue;
            }
            // Standard plan: G1-G22 / D1-D22
            if (preg_match('/^[GD]\d+$/', $rayon)) {
                $racks[$rayon][] = $palette;
            } else {
                $otherRacks[$rayon][] = $palette;
            }
        }

        return $this->render('cold_room/show.html.twig', [
            'coldRoom'     => $coldRoom,
            'occupancyStats' => $occupancyStats[$coldRoom->getId()] ?? ['usedCapacity' => 0.0, 'availableCapacity' => (float) $coldRoom->getMaxCapacityTons(), 'occupancyRate' => 0.0],
            'racks'        => $racks,
            'otherRacks'   => $otherRacks,
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_cold_room_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_CHEF_STOCK')]
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
    #[IsGranted('ROLE_CHEF_STOCK')]
    public function toggle(ColdRoom $coldRoom, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('toggle' . $coldRoom->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_cold_room_index');
        }

        if ($coldRoom->isActive() && $this->occupancyService->getUsedCapacity($coldRoom) > 0) {
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
