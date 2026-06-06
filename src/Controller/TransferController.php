<?php

namespace App\Controller;

use App\Entity\PaletteTransfer;
use App\Repository\ClientRepository;
use App\Repository\ColdRoomRepository;
use App\Repository\PaletteRepository;
use App\Repository\PaletteTransferRepository;
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
            'client' => $request->query->get('client'),
            'codePalette' => $request->query->get('code_palette'),
            'coldRoom' => $request->query->get('cold_room'),
        ];

        $transfers = $repo->findByControleurWithFilters($this->getUser(), $filters);

        return $this->render('transfer/index.html.twig', [
            'transfers' => $transfers,
            'clients' => $clientRepo->findBy(['isActive' => true], ['companyName' => 'ASC']),
            'coldRooms' => $coldRoomRepo->findActive(),
            'filters' => $filters,
        ]);
    }

    #[Route('/nouveau', name: 'app_transfer_new', methods: ['GET', 'POST'])]
    public function new(Request $request, PaletteRepository $paletteRepo, ColdRoomRepository $coldRoomRepo, EntityManagerInterface $em): Response
    {
        $palette = null;
        $coldRooms = $coldRoomRepo->findActive();

        if ($request->isMethod('POST')) {
            $code = $request->request->get('code_palette');
            $palette = $paletteRepo->findOneBy(['codePalette' => $code]);

            if ($palette && $request->request->has('confirm')) {
                $newColdRoom = $coldRoomRepo->find($request->request->get('new_cold_room'));
                $newRayon = $request->request->get('new_rayon');

                if (!$newColdRoom) {
                    $this->addFlash('error', 'Chambre invalide.');
                    return $this->redirectToRoute('app_transfer_new');
                }

                $transfer = new PaletteTransfer();
                $transfer->setPalette($palette);
                $transfer->setFromColdRoom($palette->getColdRoom());
                $transfer->setFromRayon($palette->getRayon());
                $transfer->setToColdRoom($newColdRoom);
                $transfer->setToRayon($newRayon);
                $transfer->setTransferredBy($this->getUser());

                $palette->setColdRoom($newColdRoom);
                $palette->setRayon($newRayon);

                $em->persist($transfer);
                $em->flush();

                $this->addFlash('success', 'Transfert effectué avec succès.');
                return $this->redirectToRoute('app_transfer_index');
            }

            if (!$palette) {
                $this->addFlash('error', 'Palette introuvable.');
            }
        }

        return $this->render('transfer/new.html.twig', [
            'palette' => $palette,
            'coldRooms' => $coldRooms,
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
            'id' => $palette->getId(),
            'code' => $palette->getCodePalette(),
            'client' => $palette->getOperation()->getClient()->getCompanyName(),
            'espece' => $palette->getEspece(),
            'qualite' => $palette->getQualite(),
            'moule' => $palette->getMoule(),
            'famille' => $palette->getFamille(),
            'cartons' => $palette->getCartonsRestants(),
            'poids' => round((float)$palette->getPoidsRestant() * 1000),
            'coldRoom' => $palette->getColdRoom()?->getName(),
            'coldRoomId' => $palette->getColdRoom()?->getId(),
            'rayon' => $palette->getRayon(),
        ]);
    }
}
