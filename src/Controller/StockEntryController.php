<?php

namespace App\Controller;

use App\Entity\StockEntry;
use App\Form\StockEntryType;
use App\Repository\StockEntryRepository;
use App\Security\Voter\StockEntryVoter;
use App\Service\StockService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/stock/entrees')]
class StockEntryController extends AbstractController
{
    public function __construct(
        private StockService $stockService,
        private EntityManagerInterface $em
    ) {}

    #[Route('', name: 'app_stock_entry_index', methods: ['GET'])]
    #[IsGranted(StockEntryVoter::VIEW)]
    public function index(StockEntryRepository $repository): Response
    {
        $entries = $repository->findBy([], ['createdAt' => 'DESC']);

        return $this->render('stock_entry/index.html.twig', [
            'entries' => $entries,
        ]);
    }

    #[Route('/nouveau', name: 'app_stock_entry_new', methods: ['GET', 'POST'])]
    #[IsGranted(StockEntryVoter::CREATE)]
    public function new(Request $request): Response
    {
        $entry = new StockEntry();
        $form = $this->createForm(StockEntryType::class, $entry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Generate bon de réception number
                $entry->setBonReceptionNumber($this->generateBonReceptionNumber());
                $this->stockService->createEntry($entry, $this->getUser());
                $this->addFlash('success', 'Entrée en stock créée avec succès. N° Bon: ' . $entry->getBonReceptionNumber());
                return $this->redirectToRoute('app_stock_entry_index');
            } catch (\Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('stock_entry/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_stock_entry_show', methods: ['GET'])]
    #[IsGranted(StockEntryVoter::VIEW)]
    public function show(StockEntry $entry): Response
    {
        return $this->render('stock_entry/show.html.twig', [
            'entry' => $entry,
        ]);
    }

    #[Route('/{id}/bon-reception', name: 'app_stock_entry_bon_reception', methods: ['GET'])]
    #[IsGranted(StockEntryVoter::VIEW)]
    public function bonReception(StockEntry $entry): Response
    {
        return $this->render('stock_entry/bon_reception.html.twig', [
            'entry' => $entry,
        ]);
    }

    #[Route('/{id}/valider', name: 'app_stock_entry_validate', methods: ['POST'])]
    #[IsGranted(StockEntryVoter::VALIDATE)]
    public function validate(StockEntry $entry, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('validate' . $entry->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_stock_entry_index');
        }

        try {
            $this->stockService->validateEntry($entry, $this->getUser());
            $this->addFlash('success', 'Entrée validée avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_stock_entry_index');
    }

    #[Route('/{id}/rejeter', name: 'app_stock_entry_reject', methods: ['POST'])]
    #[IsGranted(StockEntryVoter::REJECT)]
    public function reject(StockEntry $entry, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('reject' . $entry->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_stock_entry_index');
        }

        $reason = $request->request->get('reason', '');
        if (empty($reason)) {
            $this->addFlash('error', 'Le motif de rejet est obligatoire.');
            return $this->redirectToRoute('app_stock_entry_index');
        }

        try {
            $this->stockService->rejectEntry($entry, $this->getUser(), $reason);
            $this->addFlash('success', 'Entrée rejetée.');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_stock_entry_index');
    }

    private function generateBonReceptionNumber(): string
    {
        $year = date('Y');
        $month = date('m');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "BR-{$year}{$month}-{$random}";
    }
}
