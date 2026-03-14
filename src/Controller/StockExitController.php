<?php

namespace App\Controller;

use App\Entity\StockExit;
use App\Form\StockExitType;
use App\Repository\StockExitRepository;
use App\Security\Voter\StockExitVoter;
use App\Service\StockService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/stock/sorties')]
class StockExitController extends AbstractController
{
    public function __construct(
        private StockService $stockService
    ) {}

    #[Route('', name: 'app_stock_exit_index', methods: ['GET'])]
    #[IsGranted(StockExitVoter::VIEW)]
    public function index(StockExitRepository $repository): Response
    {
        $exits = $repository->findBy([], ['createdAt' => 'DESC']);

        return $this->render('stock_exit/index.html.twig', [
            'exits' => $exits,
        ]);
    }

    #[Route('/nouveau', name: 'app_stock_exit_new', methods: ['GET', 'POST'])]
    #[IsGranted(StockExitVoter::CREATE)]
    public function new(Request $request): Response
    {
        $exit = new StockExit();
        $form = $this->createForm(StockExitType::class, $exit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $exit->setBonLivraisonNumber($this->generateBonLivraisonNumber());
                $this->stockService->createExit($exit, $this->getUser());
                $this->addFlash('success', 'Sortie de stock créée avec succès. N° BL: ' . $exit->getBonLivraisonNumber());
                return $this->redirectToRoute('app_stock_exit_index');
            } catch (\Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('stock_exit/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_stock_exit_show', methods: ['GET'])]
    #[IsGranted(StockExitVoter::VIEW)]
    public function show(StockExit $exit): Response
    {
        return $this->render('stock_exit/show.html.twig', [
            'exit' => $exit,
        ]);
    }

    #[Route('/{id}/bon-livraison', name: 'app_stock_exit_bon_livraison', methods: ['GET'])]
    #[IsGranted(StockExitVoter::VIEW)]
    public function bonLivraison(StockExit $exit): Response
    {
        return $this->render('stock_exit/bon_livraison.html.twig', [
            'exit' => $exit,
        ]);
    }

    #[Route('/{id}/fiche-charge', name: 'app_stock_exit_fiche_charge', methods: ['GET'])]
    #[IsGranted(StockExitVoter::VIEW)]
    public function ficheCharge(StockExit $exit): Response
    {
        return $this->render('stock_exit/fiche_charge.html.twig', [
            'exit' => $exit,
        ]);
    }

    #[Route('/{id}/valider', name: 'app_stock_exit_validate', methods: ['POST'])]
    #[IsGranted(StockExitVoter::VALIDATE)]
    public function validate(StockExit $exit, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('validate' . $exit->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_stock_exit_index');
        }

        try {
            $this->stockService->validateExit($exit, $this->getUser());
            $this->addFlash('success', 'Sortie validée avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_stock_exit_index');
    }

    #[Route('/{id}/rejeter', name: 'app_stock_exit_reject', methods: ['POST'])]
    #[IsGranted(StockExitVoter::REJECT)]
    public function reject(StockExit $exit, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('reject' . $exit->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_stock_exit_index');
        }

        $reason = $request->request->get('reason', '');
        if (empty($reason)) {
            $this->addFlash('error', 'Le motif de rejet est obligatoire.');
            return $this->redirectToRoute('app_stock_exit_index');
        }

        try {
            $this->stockService->rejectExit($exit, $this->getUser(), $reason);
            $this->addFlash('success', 'Sortie rejetée.');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_stock_exit_index');
    }

    private function generateBonLivraisonNumber(): string
    {
        $year = date('Y');
        $month = date('m');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "BL-{$year}{$month}-{$random}";
    }
}
