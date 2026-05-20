<?php

namespace App\Controller;

use App\Entity\FicheDecharge;
use App\Enum\FicheStatus;
use App\Enum\PaletteStatus;
use App\Enum\StockStatus;
use App\Repository\FicheDechargeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/fiches')]
class FicheController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('', name: 'app_fiche_index')]
    #[IsGranted('ROLE_CONTROLEUR')]
    public function index(FicheDechargeRepository $repo): Response
    {
        $user = $this->getUser();
        if ($this->isGranted('ROLE_CHEF_STOCK')) {
            $fiches = $repo->findBy([], ['createdAt' => 'DESC']);
        } else {
            $fiches = $repo->findByControleur($user);
        }

        return $this->render('fiche/index.html.twig', ['fiches' => $fiches]);
    }

    #[Route('/{id}', name: 'app_fiche_show')]
    #[IsGranted('ROLE_CONTROLEUR')]
    public function show(FicheDecharge $fiche): Response
    {
        return $this->render('fiche/show.html.twig', ['fiche' => $fiche]);
    }

    #[Route('/{id}/envoyer-validation', name: 'app_fiche_submit', methods: ['POST'])]
    #[IsGranted('ROLE_CONTROLEUR')]
    public function submitForValidation(FicheDecharge $fiche, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('submit' . $fiche->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_fiche_show', ['id' => $fiche->getId()]);
        }

        $fiche->setStatus(FicheStatus::EN_ATTENTE_VALIDATION);
        // Le formulaire peut envoyer le champ sous différents noms (observations/commentaire).
        // On prend en priorité 'commentaire' puis 'observation'.
        $commentaire = $request->request->get('commentaire');
        if ($commentaire === null || $commentaire === '') {
            $commentaire = $request->request->get('observation');
        }
        $fiche->setObservation($commentaire);

        $this->em->flush();

        $this->addFlash('success', 'Fiche envoyée pour validation.');
        return $this->redirectToRoute('app_fiche_show', ['id' => $fiche->getId()]);
    }

    #[Route('/{id}/valider', name: 'app_fiche_validate', methods: ['POST'])]
    #[IsGranted('ROLE_CHEF_STOCK')]
    public function validate(FicheDecharge $fiche, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('validate' . $fiche->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_fiche_show', ['id' => $fiche->getId()]);
        }

        $fiche->setStatus(FicheStatus::VALIDEE);
        $fiche->setValidatedBy($this->getUser());
        $fiche->setValidatedAt(new \DateTime());

        // Also validate the operation and palettes
        $operation = $fiche->getOperation();
        $operation->setStatus(StockStatus::VALIDATED);
        $operation->setValidatedBy($this->getUser());
        $operation->setValidatedAt(new \DateTime());

        foreach ($operation->getPalettes() as $palette) {
            if ($palette->getStatus() === PaletteStatus::EN_ATTENTE) {
                $palette->setStatus(PaletteStatus::VALIDEE);
            }
        }

        $this->em->flush();
        $this->addFlash('success', 'Fiche validée. Stock mis à jour.');

        return $this->redirectToRoute('app_fiche_show', ['id' => $fiche->getId()]);
    }

    #[Route('/{id}/rejeter', name: 'app_fiche_reject', methods: ['POST'])]
    #[IsGranted('ROLE_CHEF_STOCK')]
    public function reject(FicheDecharge $fiche, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('reject' . $fiche->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_fiche_show', ['id' => $fiche->getId()]);
        }

        $reason = $request->request->get('commentaire', '');
        $fiche->setStatus(FicheStatus::REJETEE);
        $fiche->setCommentaireRejet($reason);
        $this->em->flush();

        $this->addFlash('warning', 'Fiche rejetée.');
        return $this->redirectToRoute('app_fiche_show', ['id' => $fiche->getId()]);
    }
}
