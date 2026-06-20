<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Invoice;
use App\Entity\TarifClient;
use App\Entity\User;
use App\Enum\InvoiceStatus;
use App\Enum\UserRole;
use App\Repository\ClientRepository;
use App\Repository\InvoiceRepository;
use App\Repository\TarifClientRepository;
use App\Security\Voter\InvoiceVoter;
use App\Service\InvoiceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/factures')]
class InvoiceController extends AbstractController
{
    public function __construct(
        private InvoiceService $invoiceService,
        private EntityManagerInterface $em,
    ) {}

    // ─── Index (3 onglets) ────────────────────────────────────────────────

    #[Route('', name: 'app_invoice_index', methods: ['GET'])]
    #[IsGranted(InvoiceVoter::VIEW)]
    public function index(
        Request $request,
        InvoiceRepository $invoiceRepo,
        ClientRepository $clientRepo,
        TarifClientRepository $tarifRepo,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        // Sélecteur de mois (GET ?month=2026-06)
        $monthParam  = $request->query->get('month', date('Y-m'));
        [$year, $month] = array_map('intval', explode('-', $monthParam . '-00'));
        if ($year < 2000 || $month < 1 || $month > 12) {
            $year  = (int)date('Y');
            $month = (int)date('m');
        }

        // Récapitulatif mensuel
        $monthSummary = $this->invoiceService->getMonthSummary($year, $month);

        // Grille tarifaire
        $clients      = $clientRepo->findBy(['isActive' => true], ['companyName' => 'ASC']);
        $tarifClients = $tarifRepo->indexedByClientId();

        // Liste des factures
        $filters = [];
        if ($user->hasRole(UserRole::CLIENT->value)) {
            $filters['client'] = $user->getClient();
        }
        $invoices = $invoiceRepo->findWithFilters($filters);

        return $this->render('invoice/index.html.twig', [
            'monthParam'   => sprintf('%d-%02d', $year, $month),
            'year'         => $year,
            'month'        => $month,
            'monthSummary' => $monthSummary,
            'clients'      => $clients,
            'tarifClients' => $tarifClients,
            'invoices'     => $invoices,
        ]);
    }

    // ─── Générer une facture ──────────────────────────────────────────────

    #[Route('/generer', name: 'app_invoice_generate', methods: ['GET', 'POST'])]
    #[IsGranted(InvoiceVoter::CREATE)]
    public function generate(
        Request $request,
        ClientRepository $clientRepo,
        TarifClientRepository $tarifRepo,
    ): Response {
        $clients = $clientRepo->findBy(['isActive' => true], ['companyName' => 'ASC']);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('generate_invoice', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('app_invoice_generate');
            }

            $clientId    = (int)$request->request->get('client_id', 0);
            $monthStr    = $request->request->get('month', '');
            $storageType = $request->request->get('storage_type', 'sejour');
            if (!in_array($storageType, ['sejour', 'forfait'], true)) {
                $storageType = 'sejour';
            }

            $client = $clientRepo->find($clientId);
            if (!$client) {
                $this->addFlash('error', 'Client introuvable.');
                return $this->redirectToRoute('app_invoice_generate');
            }

            if (!preg_match('/^(\d{4})-(\d{2})$/', $monthStr, $m)) {
                $this->addFlash('error', 'Mois invalide.');
                return $this->redirectToRoute('app_invoice_generate');
            }

            try {
                $invoice = $this->invoiceService->generateInvoice(
                    $client, (int)$m[1], (int)$m[2], $this->getUser(), $storageType
                );
                $this->addFlash('success', 'Facture générée : ' . $invoice->getInvoiceNumber());
                return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('invoice/generate.html.twig', [
            'clients'      => $clients,
            'tarifClients' => $tarifRepo->indexedByClientId(),
            'defaultMonth' => date('Y-m'),
        ]);
    }

    // ─── Détail facture ───────────────────────────────────────────────────

    #[Route('/{id}', name: 'app_invoice_show', methods: ['GET'])]
    public function show(Invoice $invoice): Response
    {
        $this->denyAccessUnlessGranted(InvoiceVoter::VIEW, $invoice);
        return $this->render('invoice/show.html.twig', ['invoice' => $invoice]);
    }

    // ─── Valider facture ──────────────────────────────────────────────────

    #[Route('/{id}/valider', name: 'app_invoice_validate', methods: ['POST'])]
    #[IsGranted(InvoiceVoter::VALIDATE)]
    public function validate(Invoice $invoice, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('validate' . $invoice->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_invoice_index');
        }
        try {
            $this->invoiceService->validateInvoice($invoice, $this->getUser());
            $this->addFlash('success', 'Facture validée avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }
        return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getId()]);
    }

    // ─── Sauvegarder tarif d'un client ────────────────────────────────────

    #[Route('/tarif/{id}', name: 'app_invoice_save_tarif', methods: ['POST'])]
    #[IsGranted(InvoiceVoter::CREATE)]
    public function saveTarif(Client $client, Request $request, TarifClientRepository $tarifRepo): Response
    {
        if (!$this->isCsrfTokenValid('tarif_' . $client->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_invoice_index', ['_fragment' => 'tarifs']);
        }

        $tarif = $tarifRepo->findForClient($client);
        if (!$tarif) {
            $tarif = new TarifClient();
            $tarif->setClient($client);
        }

        $tarif->setPuManipEntree((float)str_replace(',', '.', $request->request->get('pu_manip_entree', '0')));
        $tarif->setPuManipSortie((float)str_replace(',', '.', $request->request->get('pu_manip_sortie', '0')));
        $tarif->setPuSejour((float)str_replace(',', '.', $request->request->get('pu_sejour', '0')));
        $tarif->setMontantForfait((float)str_replace(',', '.', $request->request->get('montant_forfait', '0')));
        $tarif->setUpdatedBy($this->getUser());

        $this->em->persist($tarif);
        $this->em->flush();

        $this->addFlash('success', 'Tarif enregistré pour ' . $client->getCompanyName() . '.');
        return $this->redirectToRoute('app_invoice_index', ['_fragment' => 'tarifs']);
    }

    // ─── Envoyer au directeur ─────────────────────────────────────────────

    #[Route('/{id}/envoyer', name: 'app_invoice_send', methods: ['POST'])]
    #[IsGranted(InvoiceVoter::SEND)]
    public function send(Invoice $invoice, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('send' . $invoice->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getId()]);
        }

        if ($invoice->getStatus() !== InvoiceStatus::DRAFT) {
            $this->addFlash('error', 'Seule une facture en brouillon peut être envoyée.');
            return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getId()]);
        }

        $invoice->setStatus(InvoiceStatus::PENDING_VALIDATION);
        $this->em->flush();

        $this->addFlash('success', 'Facture envoyée au directeur pour validation.');
        return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getId()]);
    }

    // ─── Supprimer facture ────────────────────────────────────────────────

    #[Route('/{id}/supprimer', name: 'app_invoice_delete', methods: ['POST'])]
    #[IsGranted(InvoiceVoter::DELETE)]
    public function delete(Invoice $invoice, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete' . $invoice->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getId()]);
        }

        $this->em->remove($invoice);
        $this->em->flush();

        $this->addFlash('success', 'Facture supprimée.');
        return $this->redirectToRoute('app_invoice_index');
    }

    // ─── Export PDF ───────────────────────────────────────────────────────

    #[Route('/{id}/pdf', name: 'app_invoice_pdf', methods: ['GET'])]
    #[IsGranted(InvoiceVoter::EXPORT)]
    public function exportPdf(Invoice $invoice): Response
    {
        $html = $this->renderView('invoice/pdf.html.twig', ['invoice' => $invoice]);
        return new Response($html, 200, ['Content-Type' => 'text/html']);
    }
}