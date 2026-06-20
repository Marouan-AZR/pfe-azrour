<?php

namespace App\Controller;

use App\Entity\FicheDecharge;
use App\Entity\Operation;
use App\Entity\Palette;
use App\Enum\FicheStatus;
use App\Enum\OperationType;
use App\Enum\PaletteStatus;
use App\Enum\StockStatus;
use App\Enum\TypeStockage;
use App\Repository\ClientRepository;
use App\Repository\ColdRoomRepository;
use App\Repository\OperationRepository;
use App\Repository\PaletteRepository;
use App\Repository\UserRepository;
use App\Service\AuditService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/operations')]
class OperationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private OperationRepository $operationRepo,
        private PaletteRepository $paletteRepo,
        private AuditService $auditService,
    ) {}

    // ===== ENTRÉES =====

    #[Route('/entrees', name: 'app_operation_entry_index')]
    #[IsGranted('ROLE_CONTROLEUR')]
    public function entryIndex(Request $request, ClientRepository $clientRepo): Response
    {
        $user = $this->getUser();
        $filters = [
            'client' => $request->query->get('client'),
            'date'   => $request->query->get('date'),
            'code'   => $request->query->get('code'),
        ];

        if ($this->isGranted('ROLE_CHEF_STOCK')) {
            $operations = $this->operationRepo->findByTypeWithFilters(OperationType::ENTRY, $filters);
        } else {
            $operations = $this->operationRepo->findByControleurWithFilters($user, OperationType::ENTRY, $filters);
        }

        return $this->render('operation/entry_index.html.twig', [
            'operations' => $operations,
            'clients'    => $clientRepo->findBy(['isActive' => true], ['companyName' => 'ASC']),
            'filters'    => $filters,
        ]);
    }

    #[Route('/entrees/nouveau', name: 'app_operation_entry_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_CONTROLEUR')]
    public function entryNew(
        Request $request,
        ClientRepository $clientRepo,
        ColdRoomRepository $coldRoomRepo,
        UserRepository $userRepo,
    ): Response {
        if ($request->isMethod('POST')) {
            return $this->handleEntrySubmission($request, $clientRepo, $coldRoomRepo, $userRepo);
        }

        $operationId = $request->query->get('operation');
        $existingOperation = $operationId ? $this->operationRepo->find($operationId) : null;

        return $this->render('operation/entry_new.html.twig', [
            'clients'           => $clientRepo->findBy(['isActive' => true], ['companyName' => 'ASC']),
            'coldRooms'         => $coldRoomRepo->findBy(['isActive' => true]),
            'controleurs'       => $userRepo->findByRole('ROLE_CONTROLEUR'),
            'existingOperation' => $existingOperation,
        ]);
    }

    private function handleEntrySubmission(Request $request, ClientRepository $clientRepo, ColdRoomRepository $coldRoomRepo, UserRepository $userRepo): Response
    {
        $user     = $this->getUser();
        $clientId = $request->request->get('client');
        $client   = $clientRepo->find($clientId);

        if (!$client) {
            $this->addFlash('error', 'Client invalide.');
            return $this->redirectToRoute('app_operation_entry_new');
        }

        $operationId = $request->request->get('operation_id');
        if ($operationId) {
            $operation = $this->operationRepo->find($operationId);
            $this->em->persist($operation);
        } else {
            $operation = new Operation();
            $operation->setCode(Operation::generateCode(OperationType::ENTRY));
            $operation->setType(OperationType::ENTRY);
            $operation->setClient($client);
            $operation->setCreatedBy($user);
            $operation->setCdLotClient($request->request->get('cd_lot_client'));
            $operation->setDateReception(new \DateTime($request->request->get('date_reception', 'now')));

            if ($this->isGranted('ROLE_CHEF_STOCK')) {
                $controleurId = $request->request->get('controleur');
                if ($controleurId) {
                    $operation->setControleur($userRepo->find($controleurId));
                    $operation->setStatus(StockStatus::EN_ATTENTE_CONTROLE);
                }
            } else {
                $operation->setControleur($user);
                $operation->setStatus(StockStatus::EN_ATTENTE_CONTROLE);
            }

            $this->em->persist($operation);
        }

        $nombrePalettes = (int)$request->request->get('nombre_palettes', 1);
        $coldRoom       = $request->request->get('cold_room') ? $coldRoomRepo->find($request->request->get('cold_room')) : null;
        $existingCount  = $operation->getPalettes()->count();

        for ($i = 1; $i <= $nombrePalettes; $i++) {
            $palette = new Palette();
            $palette->setCodePalette(Palette::generateCode($operation->getCode(), $existingCount + $i));
            $palette->setEspece($request->request->get('espece'));
            $palette->setFamille($request->request->get('famille'));
            $palette->setQualite($request->request->get('qualite'));
            $palette->setMoule($request->request->get('moule'));
            $palette->setNombreCartons((int)$request->request->get('nombre_cartons', 0));
            $palette->setPoidsCarton($request->request->get('poids_carton', '0'));
            $palette->setColdRoom($coldRoom);
            $palette->setRayon($request->request->get('rayon'));
            $operation->addPalette($palette);
            $this->em->persist($palette);
        }

        if ($request->request->get('action') === 'validate' && $this->isGranted('ROLE_CHEF_STOCK')) {
            $operation->setStatus(StockStatus::VALIDATED);
            $operation->setValidatedBy($user);
            $operation->setValidatedAt(new \DateTime());
            foreach ($operation->getPalettes() as $p) {
                $p->setStatus(PaletteStatus::VALIDEE);
            }
        }

        if ($operation->getControleur() && !$operation->getFicheDecharge()) {
            $fiche = new FicheDecharge();
            $fiche->setNumero(FicheDecharge::generateNumero());
            $fiche->setOperation($operation);
            $fiche->setControleur($operation->getControleur());
            $fiche->setStatus(FicheStatus::EN_COURS_CONTROLE);
            $operation->setFicheDecharge($fiche);
            $this->em->persist($fiche);
        }

        $this->em->flush();
        $this->auditService->log('operation_created', $operation, $this->getUser(), null, ['code' => $operation->getCode()]);

        $this->addFlash('success', sprintf('Opération %s créée avec %d palette(s).', $operation->getCode(), $nombrePalettes));
        return $this->redirectToRoute('app_operation_show', ['id' => $operation->getId(), 'print_tickets' => 1]);
    }

    // ===== DÉTAILS OPÉRATION =====

    #[Route('/{id}', name: 'app_operation_show', methods: ['GET'], requirements: ['id' => '\\d+'])]
    #[IsGranted('ROLE_CONTROLEUR')]
    public function show(Operation $operation): Response
    {
        $decisionBy = $operation->getValidatedBy();

        // Fallback pour les opérations rejetées antérieures au correctif : chercher dans l'audit log
        if ($decisionBy === null && $operation->isRejected()) {
            $log = $this->em->getRepository(\App\Entity\AuditLog::class)->findOneBy(
                ['action' => 'operation_rejected', 'entityId' => $operation->getId()],
                ['createdAt' => 'DESC']
            );
            if ($log) {
                $decisionBy = $log->getUser();
            }
        }

        return $this->render('operation/show.html.twig', [
            'operation'  => $operation,
            'decisionBy' => $decisionBy,
        ]);
    }

    // ===== TICKET PALETTE =====

    #[Route('/{id}/palette/{paletteId}/ticket', name: 'app_operation_palette_ticket', methods: ['GET'])]
    #[IsGranted('ROLE_CONTROLEUR')]
    public function ticketPalette(Operation $operation, int $paletteId): Response
    {
        $palette = $this->paletteRepo->find($paletteId);
        if (!$palette || $palette->getOperation() !== $operation) {
            throw $this->createNotFoundException('Palette introuvable.');
        }
        return $this->render('operation/ticket_palette.html.twig', [
            'operation' => $operation,
            'palette'   => $palette,
        ]);
    }

    // ===== SUPPRESSION PALETTE =====

    #[Route('/{id}/palette/{paletteId}/supprimer', name: 'app_operation_palette_delete', methods: ['POST'])]
    #[IsGranted('ROLE_CONTROLEUR')]
    public function deletePalette(Operation $operation, int $paletteId, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete_palette' . $paletteId, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_operation_show', ['id' => $operation->getId()]);
        }

        $palette = $this->paletteRepo->find($paletteId);
        if (!$palette || $palette->getOperation() !== $operation) {
            throw $this->createNotFoundException('Palette introuvable.');
        }

        $code = $palette->getCodePalette();

        // Restaurer les cartons sur la palette source UNIQUEMENT si la palette de sortie
        // était validée, c'est-à-dire que sortir() a bien été appelé lors de la validation.
        // Pour une palette non-validée, le stock source n'a jamais été décrémenté.
        $source = $palette->getSourcePalette();
        if ($source !== null && $palette->getStatus() === PaletteStatus::VALIDEE) {
            $restored    = $palette->getCartonsReellementSortis() ?? $palette->getNombreCartons();
            $newRestants = $source->getCartonsRestants() + $restored;
            $source->setCartonsRestants($newRestants);
            $source->setPoidsRestant((string)($newRestants * (float) $source->getPoidsCarton()));

            // Recalculer le statut de la palette source
            if ($newRestants >= $source->getNombreCartons()) {
                $source->setStatus(PaletteStatus::VALIDEE);
            } elseif ($newRestants > 0) {
                $source->setStatus(PaletteStatus::SORTIE_PARTIELLE);
            }
        }

        // Supprimer les transferts liés à cette palette
        foreach ($palette->getTransfers() as $transfer) {
            $this->em->remove($transfer);
        }

        $this->em->remove($palette);
        $this->em->flush();

        $this->addFlash('success', 'Palette ' . $code . ' supprimée.');
        return $this->redirectToRoute('app_operation_show', ['id' => $operation->getId()]);
    }

    // ===== SUPPRESSION =====

    #[Route('/{id}/supprimer', name: 'app_operation_delete', methods: ['POST'])]
    #[IsGranted('ROLE_CONTROLEUR')]
    public function delete(Operation $operation, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete' . $operation->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_operation_show', ['id' => $operation->getId()]);
        }

        $code      = $operation->getCode();
        $type      = $operation->getType();
        $isValidated = $operation->isValidated();

        // Pour les sorties validées : restaurer le stock des palettes sources
        if ($type === OperationType::EXIT && $isValidated) {
            foreach ($operation->getPalettes() as $palette) {
                $source = $palette->getSourcePalette();
                if ($source !== null) {
                    $restored = $palette->getCartonsReellementSortis() ?? $palette->getNombreCartons();
                    $newRestants = $source->getCartonsRestants() + $restored;
                    $source->setCartonsRestants($newRestants);
                    $source->setPoidsRestant((string)($newRestants * (float) $source->getPoidsCarton()));
                    if (in_array($source->getStatus(), [PaletteStatus::SORTIE_COMPLETE, PaletteStatus::SORTIE_PARTIELLE], true)) {
                        $source->setStatus(PaletteStatus::VALIDEE);
                    }
                }
            }
        }

        $this->auditService->log('operation_deleted', $operation, $this->getUser(), null, [
            'code'   => $code,
            'type'   => $type->value,
            'status' => $operation->getStatus()->value,
        ]);

        $this->em->remove($operation);
        $this->em->flush();

        $this->addFlash('success', 'Opération ' . $code . ' supprimée. Le stock a été mis à jour.');

        return $this->redirectToRoute(
            $type === OperationType::ENTRY ? 'app_operation_entry_index' : 'app_operation_exit_index'
        );
    }

    // ===== INFOS LOGISTIQUES ENTRÉE =====

    #[Route('/{id}/save-logistique-entree', name: 'app_operation_save_logistique_entree', methods: ['POST'])]
    #[IsGranted('ROLE_CONTROLEUR')]
    public function saveLogistiqueEntree(Operation $operation, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('logistique_entree' . $operation->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_operation_show', ['id' => $operation->getId()]);
        }

        $matricule        = $request->request->get('matricule') ?: null;
        $numeroConteneur  = $request->request->get('numero_conteneur') ?: null;
        $numeroAgrement   = $operation->getClient()->getNumeroAgrement();
        $temperature      = $request->request->get('temperature') ?: null;
        $carliste         = $request->request->get('carliste') ?: null;
        $heureDebut       = $request->request->get('heure_debut') ?: null;
        $heureFin         = $request->request->get('heure_fin') ?: null;

        $operation->setImmatriculation($matricule);
        $operation->setNumeroConteneur($numeroConteneur);
        $operation->setNumeroAgrement($numeroAgrement);
        $operation->setTemperature($temperature);
        $operation->setCarliste($carliste);
        $operation->setHeureDebutChargement($heureDebut);
        $operation->setHeureFinChargement($heureFin);

        // Sync vers la fiche de décharge si elle existe
        $fiche = $operation->getFicheDecharge();
        if ($fiche !== null) {
            $fiche->setMatricule($matricule);
            $fiche->setNumeroConteneur($numeroConteneur);
            $fiche->setNumeroAgrement($numeroAgrement);
            $fiche->setTemperature($temperature);
            $fiche->setCarliste($carliste);
            $fiche->setHeureDebutChargement($heureDebut);
            $fiche->setHeureFinChargement($heureFin);
        }

        $this->em->flush();
        $this->addFlash('success', 'Informations logistiques enregistrées.');
        return $this->redirectToRoute('app_operation_show', ['id' => $operation->getId()]);
    }

    // ===== TICKETS GROUPÉS =====

    #[Route('/{id}/tickets-impression', name: 'app_operation_tickets_bulk', methods: ['GET'])]
    #[IsGranted('ROLE_CONTROLEUR')]
    public function ticketsBulk(Operation $operation, Request $request): Response
    {
        $ids      = array_filter(array_map('intval', (array) $request->query->all('palettes')));
        $palettes = array_filter(
            $operation->getPalettes()->toArray(),
            fn($p) => in_array($p->getId(), $ids, true)
        );

        if (empty($palettes)) {
            $this->addFlash('error', 'Aucune palette sélectionnée.');
            return $this->redirectToRoute('app_operation_show', ['id' => $operation->getId()]);
        }

        return $this->render('operation/tickets_bulk.html.twig', [
            'operation' => $operation,
            'palettes'  => array_values($palettes),
        ]);
    }

    // ===== MODIFICATION PALETTE =====

    #[Route('/{id}/palette/{paletteId}/modifier', name: 'app_operation_palette_edit', methods: ['POST'])]
    #[IsGranted('ROLE_CONTROLEUR')]
    public function editPalette(Operation $operation, int $paletteId, Request $request): Response
    {
        $palette = $this->paletteRepo->find($paletteId);
        if (!$palette || $palette->getOperation() !== $operation) {
            $this->addFlash('error', 'Palette introuvable.');
            return $this->redirectToRoute('app_operation_show', ['id' => $operation->getId()]);
        }

        if (!$this->isGranted('ROLE_CHEF_STOCK') && $operation->isValidated()) {
            $this->addFlash('error', 'Modification impossible après validation.');
            return $this->redirectToRoute('app_operation_show', ['id' => $operation->getId()]);
        }

        $palette->setEspece($request->request->get('espece', $palette->getEspece()));
        $palette->setFamille($request->request->get('famille'));
        $palette->setQualite($request->request->get('qualite'));
        $palette->setMoule($request->request->get('moule'));

        $newCartons    = (int)$request->request->get('nombre_cartons', $palette->getNombreCartons());
        $newPoidsCarton = $request->request->get('poids_carton', $palette->getPoidsCarton());
        $palette->setNombreCartons($newCartons);
        $palette->setPoidsCarton($newPoidsCarton);

        if ($request->request->get('cold_room')) {
            $coldRoom = $this->em->getRepository(\App\Entity\ColdRoom::class)->find($request->request->get('cold_room'));
            $palette->setColdRoom($coldRoom);
        }
        $palette->setRayon($request->request->get('rayon'));
        $palette->setObservation($request->request->get('observation'));

        $tsValue = $request->request->get('type_stockage', '');
        if ($tsValue !== '') {
            try { $palette->setTypeStockage(TypeStockage::from($tsValue)); } catch (\ValueError) {}
        } else {
            $palette->setTypeStockage(null);
        }

        $this->em->flush();
        $this->addFlash('success', 'Palette modifiée.');
        return $this->redirectToRoute('app_operation_show', ['id' => $operation->getId()]);
    }

    // ===== VALIDATION / REJET (Chef de Stock) =====

    #[Route('/{id}/valider', name: 'app_operation_validate', methods: ['POST'])]
    #[IsGranted('ROLE_CHEF_STOCK')]
    public function validate(Operation $operation, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('validate' . $operation->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_operation_show', ['id' => $operation->getId()]);
        }

        $user = $this->getUser();
        $operation->setStatus(StockStatus::VALIDATED);
        $operation->setValidatedBy($user);
        $operation->setValidatedAt(new \DateTime());

        // Appliquer le type de stockage sélectionné par le chef de stock (entrées uniquement)
        $tsValue = $request->request->get('type_stockage', '');
        if ($tsValue !== '') {
            try {
                $typeStockage = TypeStockage::from($tsValue);
                $operation->setTypeStockage($typeStockage);
                foreach ($operation->getPalettes() as $p) {
                    if ($p->getTypeStockage() === null) {
                        $p->setTypeStockage($typeStockage);
                    }
                }
            } catch (\ValueError) { /* valeur inconnue, on ignore */ }
        }

        foreach ($operation->getPalettes() as $exitPalette) {
            if (in_array($exitPalette->getStatus(), [PaletteStatus::REJETEE, PaletteStatus::ANNULEE])) {
                continue;
            }

            $exitPalette->setStatus(PaletteStatus::VALIDEE);

            // Déduire le stock de la palette source
            $sourcePalette = $exitPalette->getSourcePalette();
            if ($sourcePalette) {
                $cartonsADeduire = $exitPalette->getCartonsReellementSortis() ?? $exitPalette->getNombreCartons();
                $sourcePalette->sortir($cartonsADeduire);
            }
        }

        // Mettre à jour la fiche de charge
        if ($operation->getFicheDecharge()) {
            $fiche = $operation->getFicheDecharge();
            $fiche->setStatus(FicheStatus::VALIDEE);
            $fiche->setValidatedBy($user);
            $fiche->setValidatedAt(new \DateTime());
        }

        $this->em->flush();
        $this->auditService->log('exit_operation_validated', $operation, $user, null, ['code' => $operation->getCode()]);

        $this->addFlash('success', 'Opération validée. Stock mis à jour.');
        return $this->redirectToRoute('app_operation_show', ['id' => $operation->getId()]);
    }

    #[Route('/{id}/rejeter', name: 'app_operation_reject', methods: ['POST'])]
    #[IsGranted('ROLE_CHEF_STOCK')]
    public function reject(Operation $operation, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('reject' . $operation->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_operation_show', ['id' => $operation->getId()]);
        }

        $reason = trim($request->request->get('reason', ''));
        if (empty($reason)) {
            $this->addFlash('error', 'Le motif de rejet est obligatoire.');
            return $this->redirectToRoute('app_operation_show', ['id' => $operation->getId()]);
        }

        $operation->setStatus(StockStatus::REJECTED);
        $operation->setRejectionReason($reason);
        $operation->setValidatedBy($this->getUser());
        $operation->setValidatedAt(new \DateTime());

        // Mettre à jour les statuts des palettes selon le type d'opération
        foreach ($operation->getPalettes() as $palette) {
            if ($operation->getType() === OperationType::ENTRY) {
                // Entrée rejetée : toutes les palettes passent à REJETEE
                $palette->setStatus(PaletteStatus::REJETEE);
            } else {
                // Sortie rejetée : toutes les palettes non explicitement rejetées sont annulées (retour en stock)
                // Le stock source n'est PAS touché car sortir() n'est appelé qu'à la validation
                if ($palette->getStatus() !== PaletteStatus::REJETEE) {
                    $palette->setStatus(PaletteStatus::ANNULEE);
                }
            }
        }

        if ($operation->getFicheDecharge()) {
            $operation->getFicheDecharge()->setStatus(FicheStatus::REJETEE);
            $operation->getFicheDecharge()->setCommentaireRejet($reason);
        }

        $this->em->flush();
        $this->auditService->log('operation_rejected', $operation, $this->getUser(), null, [
            'code' => $operation->getCode(), 'reason' => $reason,
        ]);

        $this->addFlash('warning', 'Opération rejetée.');
        $redirect = $operation->getType() === OperationType::EXIT
            ? 'app_operation_exit_index'
            : 'app_operation_entry_index';
        return $this->redirectToRoute($redirect);
    }

    // ===== FICHE DE DÉCHARGE =====

    #[Route('/{id}/generer-fiche', name: 'app_operation_generate_fiche', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_CONTROLEUR')]
    public function generateFiche(Operation $operation, Request $request, UserRepository $userRepo): Response
    {
        if ($operation->getFicheDecharge()) {
            $ficheExistante = $operation->getFicheDecharge();
            if ($ficheExistante->getControleur() !== $operation->getControleur()) {
                $operation->setControleur($ficheExistante->getControleur());
                $this->em->persist($operation);
                $this->em->flush();
            }
            return $this->redirectToRoute('app_fiche_show', ['id' => $ficheExistante->getId()]);
        }

        if ($request->isMethod('POST')) {
            $controleurId = $request->request->get('controleur');
            $controleur   = $controleurId ? $userRepo->find($controleurId) : $this->getUser();

            $fiche = new FicheDecharge();
            $fiche->setNumero(FicheDecharge::generateNumero());
            $fiche->setOperation($operation);
            $fiche->setControleur($controleur);
            $fiche->setStatus(FicheStatus::EN_COURS_CONTROLE);
            $operation->setControleur($controleur);

            $this->em->persist($fiche);
            $this->em->persist($operation);
            $this->em->flush();

            $this->addFlash('success', 'Fiche générée et assignée.');
            return $this->redirectToRoute('app_fiche_show', ['id' => $fiche->getId()]);
        }

        return $this->render('operation/generate_fiche.html.twig', [
            'operation'   => $operation,
            'controleurs' => $userRepo->findByRole('ROLE_CONTROLEUR'),
        ]);
    }

    // ===== BON D'ENTRÉE =====

    #[Route('/{id}/bon-entree', name: 'app_operation_bon_entree', methods: ['GET'])]
    #[IsGranted('ROLE_CONTROLEUR')]
    public function bonEntree(Operation $operation): Response
    {
        if (!$operation->isValidated()) {
            $this->addFlash('error', "Le bon d'entrée ne peut être généré qu'après validation.");
            return $this->redirectToRoute('app_operation_show', ['id' => $operation->getId()]);
        }
        return $this->render('operation/bon_entree.html.twig', ['operation' => $operation]);
    }

    // ===== API =====

    #[Route('/api/rayons/{coldRoomId}', name: 'app_operation_api_rayons', methods: ['GET'])]
    public function apiRayons(int $coldRoomId = 0): JsonResponse
    {
        $rayons = [];
        for ($i = 1; $i <= 22; $i++) { $rayons[] = 'G' . $i; }
        for ($i = 1; $i <= 22; $i++) { $rayons[] = 'D' . $i; }
        return $this->json($rayons);
    }

    // ===== SORTIES – LISTE =====

    #[Route('/sorties', name: 'app_operation_exit_index')]
    #[IsGranted('ROLE_CONTROLEUR')]
    public function exitIndex(Request $request, ClientRepository $clientRepo): Response
    {
        $user    = $this->getUser();
        $filters = [
            'client' => $request->query->get('client'),
            'date'   => $request->query->get('date'),
            'code'   => $request->query->get('code'),
            'status' => $request->query->get('status'),
        ];

        if ($this->isGranted('ROLE_CHEF_STOCK')) {
            $operations = $this->operationRepo->findByTypeWithFilters(OperationType::EXIT, $filters);
        } else {
            $operations = $this->operationRepo->findByControleurWithFilters($user, OperationType::EXIT, $filters);
        }

        // KPI counts
        $kpi = ['total' => 0, 'en_attente_controle' => 0, 'en_attente_validation' => 0, 'validated' => 0, 'rejected' => 0];
        foreach ($operations as $op) {
            $kpi['total']++;
            $sv = $op->getStatus()->value;
            if (isset($kpi[$sv])) { $kpi[$sv]++; }
            elseif ($sv === 'pending') { $kpi['en_attente_controle']++; }
        }

        return $this->render('operation/exit_index.html.twig', [
            'operations' => $operations,
            'clients'    => $clientRepo->findBy(['isActive' => true], ['companyName' => 'ASC']),
            'filters'    => $filters,
            'kpi'        => $kpi,
        ]);
    }

    // ===== SORTIES – PRÉPARATION (Chef de Stock) =====

    #[Route('/sorties/preparer', name: 'app_operation_exit_prepare', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_CHEF_STOCK')]
    public function exitPrepare(
        Request $request,
        ClientRepository $clientRepo,
        ColdRoomRepository $coldRoomRepo,
        UserRepository $userRepo,
    ): Response {
        $clientId      = $request->query->get('client') ?? $request->request->get('client');
        $selectedClient = $clientId ? $clientRepo->find($clientId) : null;

        $palettes     = [];
        $filterValues = [];
        $filters      = [];

        if ($selectedClient) {
            $filters = [
                'espece'        => $request->query->get('espece'),
                'qualite'       => $request->query->get('qualite'),
                'moule'         => $request->query->get('moule'),
                'famille'       => $request->query->get('famille'),
                'coldRoom'      => $request->query->get('cold_room'),
                'rayon'         => $request->query->get('rayon'),
                'cdLotClient'   => $request->query->get('cd_lot'),
                'dateReception' => $request->query->get('date_reception'),
            ];
            $palettes     = $this->paletteRepo->findAvailableByClient($selectedClient, array_filter($filters));
            $filterValues = $this->paletteRepo->getFilterValuesForClient($selectedClient);
        }

        // POST: create exit operation
        if ($request->isMethod('POST') && $request->request->has('selected_palettes')) {
            $selectedIds  = $request->request->all('selected_palettes');
            $controleurId = $request->request->get('controleur');

            if (empty($selectedIds) || !$selectedClient) {
                $this->addFlash('error', 'Sélectionnez au moins une palette et un client.');
                return $this->redirectToRoute('app_operation_exit_prepare', ['client' => $clientId]);
            }

            $operation = new Operation();
            $operation->setCode(Operation::generateCode(OperationType::EXIT));
            $operation->setType(OperationType::EXIT);
            $operation->setClient($selectedClient);
            $operation->setCreatedBy($this->getUser());
            $operation->setDateReception(new \DateTime());
            $operation->setStatus(StockStatus::EN_ATTENTE_CONTROLE);

            if ($controleurId) {
                $operation->setControleur($userRepo->find($controleurId));
            }

            $this->em->persist($operation);

            $index = 1;
            foreach ($selectedIds as $paletteId) {
                $sourcePalette = $this->paletteRepo->find($paletteId);
                if (!$sourcePalette || !$sourcePalette->isStockAvailable()) {
                    continue;
                }

                $exitPalette = new Palette();
                $exitPalette->setCodePalette(Palette::generateCode($operation->getCode(), $index));
                $exitPalette->setEspece($sourcePalette->getEspece());
                $exitPalette->setFamille($sourcePalette->getFamille());
                $exitPalette->setQualite($sourcePalette->getQualite());
                $exitPalette->setMoule($sourcePalette->getMoule());
                $exitPalette->setNombreCartons($sourcePalette->getCartonsRestants());
                $exitPalette->setPoidsCarton($sourcePalette->getPoidsCarton());
                $exitPalette->setColdRoom($sourcePalette->getColdRoom());
                $exitPalette->setRayon($sourcePalette->getRayon());
                $exitPalette->setSourcePalette($sourcePalette);
                $exitPalette->setStatus(PaletteStatus::EN_ATTENTE);
                $operation->addPalette($exitPalette);
                $this->em->persist($exitPalette);
                $index++;
            }

            // Générer la fiche de charge
            if ($operation->getControleur()) {
                $fiche = new FicheDecharge();
                $fiche->setNumero(FicheDecharge::generateNumero());
                $fiche->setOperation($operation);
                $fiche->setControleur($operation->getControleur());
                $fiche->setStatus(FicheStatus::EN_COURS_CONTROLE);
                $fiche->setTemperature($request->request->get('temperature'));
                $fiche->setMatricule($request->request->get('matricule'));
                $fiche->setNumeroConteneur($request->request->get('numero_conteneur'));
                $fiche->setNumeroAgrement($request->request->get('numero_agrement'));
                $fiche->setCarliste($request->request->get('carliste'));
                $fiche->setHeureDebutChargement($request->request->get('heure_debut'));
                $fiche->setHeureFinChargement($request->request->get('heure_fin'));
                $operation->setFicheDecharge($fiche);
                $this->em->persist($fiche);
            }

            $this->em->flush();
            $this->auditService->log('exit_operation_created', $operation, $this->getUser(), null, [
                'code' => $operation->getCode(),
            ]);

            $this->addFlash('success', 'Opération de sortie créée : ' . $operation->getCode());
            return $this->redirectToRoute('app_operation_show', ['id' => $operation->getId()]);
        }

        return $this->render('operation/exit_prepare.html.twig', [
            'clients'        => $clientRepo->findBy(['isActive' => true], ['companyName' => 'ASC']),
            'selectedClient' => $selectedClient,
            'palettes'       => $palettes,
            'filterValues'   => $filterValues,
            'controleurs'    => $userRepo->findByRole('ROLE_CONTROLEUR'),
            'coldRooms'      => $coldRoomRepo->findBy(['isActive' => true]),
            'filters'        => $filters,
        ]);
    }

    // ===== SORTIES – INTERFACE CONTRÔLE TERRAIN =====

    #[Route('/{id}/controle-terrain', name: 'app_operation_exit_controle', methods: ['GET'])]
    #[IsGranted('ROLE_CONTROLEUR')]
    public function exitControle(Operation $operation): Response
    {
        if ($operation->getType() !== OperationType::EXIT) {
            throw $this->createNotFoundException();
        }

        // Contrôleur ne peut accéder qu'à ses propres opérations
        if (!$this->isGranted('ROLE_CHEF_STOCK') && $operation->getControleur() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $palettesRejeteesCount = 0;
        foreach ($operation->getPalettes() as $p) {
            if ($p->getStatus() === PaletteStatus::REJETEE) {
                $palettesRejeteesCount++;
            }
        }

        return $this->render('operation/exit_controle.html.twig', [
            'operation'             => $operation,
            'palettesRejeteesCount' => $palettesRejeteesCount,
        ]);
    }

    #[Route('/{id}/palette/{paletteId}/saisie-controle', name: 'app_operation_exit_palette_controle', methods: ['POST'])]
    #[IsGranted('ROLE_CONTROLEUR')]
    public function exitPaletteControle(Operation $operation, int $paletteId, Request $request): Response
    {
        $palette = $this->paletteRepo->find($paletteId);
        if (!$palette || $palette->getOperation() !== $operation) {
            return $this->json(['error' => 'Palette introuvable.'], 404);
        }

        if (!$this->isGranted('ROLE_CHEF_STOCK') && $operation->getControleur() !== $this->getUser()) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $action = $request->request->get('action');

        if ($action === 'rejeter') {
            $motif = trim($request->request->get('motif_rejet', ''));
            if (empty($motif)) {
                $this->addFlash('error', 'Le motif de rejet est obligatoire.');
                return $this->redirectToRoute('app_operation_exit_controle', ['id' => $operation->getId()]);
            }
            $palette->setStatus(PaletteStatus::REJETEE);
            $palette->setMotifRejet($motif);
        } else {
            $cartons = (int)$request->request->get('cartons_reellement_sortis', 0);
            if ($cartons < 0 || $cartons > $palette->getNombreCartons()) {
                $this->addFlash('error', 'Quantité invalide.');
                return $this->redirectToRoute('app_operation_exit_controle', ['id' => $operation->getId()]);
            }
            $palette->setCartonsReellementSortis($cartons);
            $palette->setObservation($request->request->get('observation'));
        }

        $this->em->flush();
        $this->addFlash('success', 'Palette mise à jour.');
        return $this->redirectToRoute('app_operation_exit_controle', ['id' => $operation->getId()]);
    }

    #[Route('/{id}/soumettre-validation', name: 'app_operation_exit_soumettre', methods: ['POST'])]
    #[IsGranted('ROLE_CONTROLEUR')]
    public function exitSoumettre(Operation $operation, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('soumettre' . $operation->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_operation_exit_controle', ['id' => $operation->getId()]);
        }

        if ($operation->getType() !== OperationType::EXIT) {
            throw $this->createNotFoundException();
        }

        if (!$this->isGranted('ROLE_CHEF_STOCK') && $operation->getControleur() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        // Annuler automatiquement les palettes non scannées/non saisies
        $nonTraitees = 0;
        foreach ($operation->getPalettes() as $p) {
            if ($p->getStatus() === PaletteStatus::EN_ATTENTE && $p->getCartonsReellementSortis() === null) {
                $p->setStatus(PaletteStatus::ANNULEE);
                $p->setMotifRejet('Non présenté lors du contrôle terrain – retour au stock');
                $nonTraitees++;
            }
        }

        if ($nonTraitees > 0) {
            $this->addFlash('info', sprintf(
                '%d palette(s) non scannée(s) ont été automatiquement annulées et retournées au stock.',
                $nonTraitees
            ));
        }

        $operation->setStatus(StockStatus::EN_ATTENTE_VALIDATION);

        if ($operation->getFicheDecharge()) {
            $fiche = $operation->getFicheDecharge();
            $fiche->setStatus(FicheStatus::EN_ATTENTE_VALIDATION);
            if ($request->request->get('matricule'))        $fiche->setMatricule($request->request->get('matricule'));
            if ($request->request->get('numero_conteneur')) $fiche->setNumeroConteneur($request->request->get('numero_conteneur'));
            if ($request->request->get('numero_agrement'))  $fiche->setNumeroAgrement($request->request->get('numero_agrement'));
            if ($request->request->get('temperature'))      $fiche->setTemperature($request->request->get('temperature'));
            if ($request->request->get('carliste'))         $fiche->setCarliste($request->request->get('carliste'));
            if ($request->request->get('heure_debut'))      $fiche->setHeureDebutChargement($request->request->get('heure_debut'));
            if ($request->request->get('heure_fin'))        $fiche->setHeureFinChargement($request->request->get('heure_fin'));
            if ($request->request->get('observation'))       $fiche->setObservation($request->request->get('observation'));
        }

        $this->em->flush();
        $this->auditService->log('exit_controle_submitted', $operation, $this->getUser(), null, [
            'code' => $operation->getCode(),
        ]);

        $this->addFlash('success', 'Contrôle soumis au chef de stock pour validation.');
        return $this->redirectToRoute('app_operation_exit_index');
    }

    // ===== BON DE SORTIE =====

    #[Route('/{id}/bon-sortie', name: 'app_operation_bon_sortie', methods: ['GET'])]
    #[IsGranted('ROLE_CONTROLEUR')]
    public function bonSortie(Operation $operation): Response
    {
        if (!$operation->isValidated()) {
            $this->addFlash('error', "Le bon de sortie ne peut être généré qu'après validation.");
            return $this->redirectToRoute('app_operation_show', ['id' => $operation->getId()]);
        }
        return $this->render('operation/bon_sortie.html.twig', ['operation' => $operation]);
    }
}
