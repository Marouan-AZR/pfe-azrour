<?php

namespace App\Controller;

use App\Entity\PaletteTransfer;
use App\Repository\ClientRepository;
use App\Repository\ColdRoomRepository;
use App\Repository\PaletteRepository;
use App\Repository\PaletteTransferRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/transferts')]
#[IsGranted('ROLE_CONTROLEUR')]
class TransferController extends AbstractController
{
    #[Route('', name: 'app_transfer_index')]
    public function index(Request $request, PaletteTransferRepository $repo, ClientRepository $clientRepo, ColdRoomRepository $coldRoomRepo): Response
    {
        $filters = [
            'client'      => $request->query->get('client'),
            'codePalette' => $request->query->get('code_palette'),
            'coldRoom'    => $request->query->get('cold_room'),
        ];

        $transfers = $repo->findAllWithFilters($filters);

        return $this->render('transfer/index.html.twig', [
            'transfers' => $transfers,
            'clients'   => $clientRepo->findBy(['isActive' => true], ['companyName' => 'ASC']),
            'coldRooms' => $coldRoomRepo->findActive(),
            'filters'   => $filters,
        ]);
    }

    #[Route('/nouveau', name: 'app_transfer_new', methods: ['GET', 'POST'])]
    public function new(Request $request, PaletteRepository $paletteRepo, ColdRoomRepository $coldRoomRepo, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST') && $request->request->has('confirm')) {
            if (!$this->isCsrfTokenValid('transfer_confirm', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('app_transfer_new');
            }

            $code    = $request->request->get('code_palette');
            $palette = $paletteRepo->findOneBy(['codePalette' => $code]);

            if (!$palette) {
                $this->addFlash('error', 'Palette introuvable : ' . $code);
                return $this->redirectToRoute('app_transfer_new');
            }

            if (!$palette->getColdRoom()) {
                $this->addFlash('error', 'Cette palette n\'a pas de localisation assignée, impossible de la transférer.');
                return $this->redirectToRoute('app_transfer_new');
            }

            $newColdRoom = $coldRoomRepo->find($request->request->get('new_cold_room'));
            $newRayon    = $request->request->get('new_rayon') ?: null;

            if (!$newColdRoom) {
                $this->addFlash('error', 'Chambre de destination invalide.');
                return $this->redirectToRoute('app_transfer_new');
            }

            $fromColdRoom = $palette->getColdRoom();
            $fromRayon    = $palette->getRayon();

            $transfer = new PaletteTransfer();
            $transfer->setPalette($palette);
            $transfer->setFromColdRoom($fromColdRoom);
            $transfer->setFromRayon($fromRayon);
            $transfer->setToColdRoom($newColdRoom);
            $transfer->setToRayon($newRayon);
            $transfer->setTransferredBy($this->getUser());

            // Mise à jour de la localisation de la palette
            $palette->setColdRoom($newColdRoom);
            $palette->setRayon($newRayon);

            $em->persist($transfer);
            $em->flush();

            $this->addFlash('success', sprintf(
                'Palette %s transférée avec succès : %s/%s → %s%s.',
                $palette->getCodePalette(),
                $fromColdRoom->getName(),
                $fromRayon ?? '—',
                $newColdRoom->getName(),
                $newRayon ? '/' . $newRayon : ''
            ));

            return $this->redirectToRoute('app_transfer_index');
        }

        return $this->render('transfer/new.html.twig', [
            'coldRooms' => $coldRoomRepo->findActive(),
        ]);
    }

    #[Route('/api/scan/{code}', name: 'app_transfer_scan', methods: ['GET'])]
    public function scan(string $code, PaletteRepository $paletteRepo): JsonResponse
    {
        $palette = $paletteRepo->findOneBy(['codePalette' => $code]);
        if (!$palette) {
            return $this->json(['error' => 'Palette introuvable'], 404);
        }

        return $this->json([
            'id'          => $palette->getId(),
            'code'        => $palette->getCodePalette(),
            'client'      => $palette->getOperation()->getClient()->getCompanyName(),
            'espece'      => $palette->getEspece(),
            'qualite'     => $palette->getQualite() ?? '—',
            'moule'       => $palette->getMoule() ?? '—',
            'famille'     => $palette->getFamille() ?? '—',
            'cartons'     => $palette->getCartonsRestants(),
            'poids'       => round((float)$palette->getPoidsRestant(), 2),
            'coldRoom'    => $palette->getColdRoom()?->getName(),
            'coldRoomId'  => $palette->getColdRoom()?->getId(),
            'rayon'       => $palette->getRayon(),
            'hasColdRoom' => $palette->getColdRoom() !== null,
        ]);
    }

    // ── Vue Chef de Stock : tous les transferts de tous les contrôleurs ──────
    #[Route('/chef-stock/transferts', name: 'app_chef_stock_transfers', methods: ['GET'])]
    #[IsGranted('ROLE_CHEF_STOCK')]
    public function chefStockIndex(
        Request $request,
        PaletteTransferRepository $repo,
        ClientRepository $clientRepo,
        ColdRoomRepository $coldRoomRepo,
        UserRepository $userRepo,
    ): Response {
        $filters = [
            'client'      => $request->query->get('client'),
            'codePalette' => $request->query->get('code_palette'),
            'coldRoom'    => $request->query->get('cold_room'),
            'controleur'  => $request->query->get('controleur'),
            'dateFrom'    => $request->query->get('date_from'),
            'dateTo'      => $request->query->get('date_to'),
        ];

        $transfers   = $repo->findAllWithFilters($filters);
        $controleurs = $userRepo->findByRole('ROLE_CONTROLEUR');

        return $this->render('transfer/chef_stock_index.html.twig', [
            'transfers'   => $transfers,
            'clients'     => $clientRepo->findBy(['isActive' => true], ['companyName' => 'ASC']),
            'coldRooms'   => $coldRoomRepo->findActive(),
            'controleurs' => $controleurs,
            'filters'     => $filters,
        ]);
    }

    #[Route('/api/rayons/{coldRoomId}', name: 'app_transfer_api_rayons', methods: ['GET'])]
    public function apiRayons(int $coldRoomId): JsonResponse
    {
        $rayons = [];
        for ($i = 1; $i <= 22; $i++) { $rayons[] = 'G' . $i; }
        for ($i = 1; $i <= 22; $i++) { $rayons[] = 'D' . $i; }
        return $this->json($rayons);
    }
}