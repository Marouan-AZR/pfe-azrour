<?php

namespace App\Controller;

use App\Entity\PaletteTransfer;
use App\Repository\ColdRoomRepository;
use App\Repository\PaletteRepository;
use App\Repository\PaletteTransferRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/transferts')]
#[IsGranted('ROLE_CONTROLEUR')]
class TransferController extends AbstractController
{
    #[Route('', name: 'app_transfer_index')]
    public function index(PaletteTransferRepository $repo): Response
    {
        $transfers = $repo->findByControleur($this->getUser());
        return $this->render('transfer/index.html.twig', ['transfers' => $transfers]);
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
}
