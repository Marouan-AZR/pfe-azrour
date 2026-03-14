<?php

namespace App\Controller;

use App\Entity\Invoice;
use App\Entity\User;
use App\Enum\InvoiceStatus;
use App\Enum\UserRole;
use App\Form\InvoiceFilterType;
use App\Form\InvoiceGenerateType;
use App\Repository\InvoiceRepository;
use App\Security\Voter\InvoiceVoter;
use App\Service\InvoiceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/factures')]
class InvoiceController extends AbstractController
{
    public function __construct(
        private InvoiceService $invoiceService
    ) {}

    #[Route('', name: 'app_invoice_index', methods: ['GET'])]
    #[IsGranted(InvoiceVoter::VIEW)]
    public function index(Request $request, InvoiceRepository $repository): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $filterForm = $this->createForm(InvoiceFilterType::class);
        $filterForm->handleRequest($request);
        
        $filters = $filterForm->getData() ?? [];
        
        // Client can only see their own invoices
        if ($user->hasRole(UserRole::CLIENT->value)) {
            $filters['client'] = $user->getClient();
        }

        $invoices = $repository->findWithFilters($filters);

        return $this->render('invoice/index.html.twig', [
            'invoices' => $invoices,
            'filterForm' => $filterForm,
        ]);
    }

    #[Route('/generer', name: 'app_invoice_generate', methods: ['GET', 'POST'])]
    #[IsGranted(InvoiceVoter::CREATE)]
    public function generate(Request $request): Response
    {
        $form = $this->createForm(InvoiceGenerateType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            
            try {
                $invoice = $this->invoiceService->generateInvoice(
                    $data['client'],
                    $data['periodStart'],
                    $data['periodEnd'],
                    $this->getUser()
                );
                
                $this->addFlash('success', 'Facture générée avec succès: ' . $invoice->getInvoiceNumber());
                return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('invoice/generate.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_invoice_show', methods: ['GET'])]
    public function show(Invoice $invoice): Response
    {
        $this->denyAccessUnlessGranted(InvoiceVoter::VIEW, $invoice);

        return $this->render('invoice/show.html.twig', [
            'invoice' => $invoice,
        ]);
    }

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

    #[Route('/{id}/pdf', name: 'app_invoice_pdf', methods: ['GET'])]
    #[IsGranted(InvoiceVoter::EXPORT)]
    public function exportPdf(Invoice $invoice): Response
    {
        // Simple HTML to PDF - in production, use a library like Dompdf or TCPDF
        $html = $this->renderView('invoice/pdf.html.twig', [
            'invoice' => $invoice,
        ]);

        return new Response($html, 200, [
            'Content-Type' => 'text/html',
        ]);
    }
}
