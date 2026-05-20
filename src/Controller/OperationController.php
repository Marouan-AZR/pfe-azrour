<?php

namespace App\Controller;

use App\Entity\FicheDecharge;
use App\Entity\Operation;
use App\Entity\Palette;
use App\Enum\FicheStatus;
use App\Enum\OperationType;
use App\Enum\PaletteStatus;
use App\Enum\StockStatus;
use App\Repository\ClientRepository;
use App\Repository\ColdRoomRepository;
use App\Repository\FicheDechargeRepository;
use App\Repository\OperationRepository;
use App\Repository\PaletteRepository;
use App\Repository\UserRepository;
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
    ) {}

    // ===== ENTRÉES =====

    #[Route('/entrees', name: 'app_operation_entry_index')]
    #[IsGranted('ROLE_CONTROLEUR')]
    public function entryIndex(): Response
    {
        $user = $this->getUser();
        if ($this->isGranted('ROLE_CHEF_STOCK')) {
            $operations = $this->operationRepo->findByType(OperationType::ENTRY);
        } else {
            $operations = $this->operationRepo->findByControleur($user, OperationType::ENTRY);
        }

        return $this->render('operation/entry_index.html.twig', [
            'operations' => $operations,
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

        // Pre-fill from existing operation if adding palettes
        $operationId = $request->query->get('operation');
        $existingOperation = $operationId ? $this->operationRepo->find($operationId) : null;

        return $this->render('operation/entry_new.html.twig', [
            'clients' => $clientRepo->findBy(['isActive' => true], ['companyName' => 'ASC']),
            'coldRooms' => $coldRoomRepo->findBy(['isActive' => true]),
            'controleurs' => $userRepo->findByRole('ROLE_CONTROLEUR'),
            'existingOperation' => $existingOperation,
        ]);
    }

    private function handleEntrySubmission(Request $request, ClientRepository $clientRepo, ColdRoomRepository $coldRoomRepo, UserRepository $userRepo): Response
    {
        $user = $this->getUser();
        $clientId = $request->request->get('client');
        $client = $clientRepo->find($clientId);

        if (!$client) {
            $this->addFlash('error', 'Client invalide.');
            return $this->redirectToRoute('app_operation_entry_new');
        }

        // Check if adding to existing operation
        $operationId = $request->request->get('operation_id');
        if ($operationId) {
            $operation = $this->operationRepo->find($operationId);
            // Ensure Doctrine persists any controller assignment changes
            $this->em->persist($operation);
        } else {
            $operation = new Operation();
            $operation->setCode(Operation::generateCode(OperationType::ENTRY));
            $operation->setType(OperationType::ENTRY);
            $operation->setClient($client);
            $operation->setCreatedBy($user);
            $operation->setCdLotClient($request->request->get('cd_lot_client'));
            $operation->setDateReception(new \DateTime($request->request->get('date_reception', 'now')));

            // Controleur assignment
            if ($this->isGranted('ROLE_CHEF_STOCK')) {
                $controleurId = $request->request->get('controleur');
                if ($controleurId) {
                    $operation->setControleur($userRepo->find($controleurId));
                }
            } else {
                $operation->setControleur($user);
            }

            $this->em->persist($operation);
        }

        // Create palettes
        $nombrePalettes = (int)$request->request->get('nombre_palettes', 1);
        $espece = $request->request->get('espece');
        $famille = $request->request->get('famille');
        $qualite = $request->request->get('qualite');
        $moule = $request->request->get('moule');
        $nombreCartons = (int)$request->request->get('nombre_cartons', 0);
        $poidsCarton = $request->request->get('poids_carton', '0');
        $coldRoomId = $request->request->get('cold_room');
        $rayon = $request->request->get('rayon');

        $coldRoom = $coldRoomId ? $coldRoomRepo->find($coldRoomId) : null;
        $existingCount = $operation->getPalettes()->count();

        for ($i = 1; $i <= $nombrePalettes; $i++) {
            $palette = new Palette();
            $palette->setCodePalette(Palette::generateCode($operation->getCode(), $existingCount + $i));
            $palette->setEspece($espece);
            $palette->setFamille($famille);
            $palette->setQualite($qualite);
            $palette->setMoule($moule);
            $palette->setNombreCartons($nombreCartons);
            $palette->setPoidsCarton($poidsCarton);
            $palette->setColdRoom($coldRoom);
            $palette->setRayon($rayon);
            $operation->addPalette($palette);
            $this->em->persist($palette);
        }

        // If chef de stock clicks "Valider"
        if ($request->request->get('action') === 'validate' && $this->isGranted('ROLE_CHEF_STOCK')) {
            $operation->setStatus(StockStatus::VALIDATED);
            $operation->setValidatedBy($user);
            $operation->setValidatedAt(new \DateTime());
            foreach ($operation->getPalettes() as $p) {
                $p->setStatus(PaletteStatus::VALIDEE);
            }
        }

        // Générer automatiquement la fiche pour l'entrée (fiche de décharge)
        // NB: la relation est OneToOne côté Operation, donc on doit aussi relier la fiche à l'opération.
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

        $this->addFlash('success', sprintf(
            'Opération %s créée avec %d palette(s).',
            $operation->getCode(),
            $nombrePalettes
        ));

        return $this->redirectToRoute('app_operation_show', ['id' => $operation->getId()]);
    }

    // ===== DÉTAILS OPÉRATION =====

    #[Route('/{id}', name: 'app_operation_show', methods: ['GET'], requirements: ['id' => '\\d+'])]
    #[IsGranted('ROLE_CONTROLEUR')]
    public function show(Operation $operation): Response
    {
        return $this->render('operation/show.html.twig', [
            'operation' => $operation,
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

        // Controleur can only edit before validation
        if (!$this->isGranted('ROLE_CHEF_STOCK') && $operation->isValidated()) {
            $this->addFlash('error', 'Modification impossible après validation.');
            return $this->redirectToRoute('app_operation_show', ['id' => $operation->getId()]);
        }

        $palette->setEspece($request->request->get('espece', $palette->getEspece()));
        $palette->setFamille($request->request->get('famille'));
        $palette->setQualite($request->request->get('qualite'));
        $palette->setMoule($request->request->get('moule'));

        $newCartons = (int)$request->request->get('nombre_cartons', $palette->getNombreCartons());
        $newPoidsCarton = $request->request->get('poids_carton', $palette->getPoidsCarton());
        $palette->setNombreCartons($newCartons);
        $palette->setPoidsCarton($newPoidsCarton);

        if ($request->request->get('cold_room')) {
            $coldRoom = $this->em->getRepository(\App\Entity\ColdRoom::class)->find($request->request->get('cold_room'));
            $palette->setColdRoom($coldRoom);
        }
        $palette->setRayon($request->request->get('rayon'));
        $palette->setObservation($request->request->get('observation'));

        $this->em->flush();
        $this->addFlash('success', 'Palette modifiée.');

        return $this->redirectToRoute('app_operation_show', ['id' => $operation->getId()]);
    }

    // ===== VALIDATION / REJET =====

    #[Route('/{id}/valider', name: 'app_operation_validate', methods: ['POST'])]
    #[IsGranted('ROLE_CHEF_STOCK')]
    public function validate(Operation $operation, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('validate' . $operation->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_operation_show', ['id' => $operation->getId()]);
        }

        $operation->setStatus(StockStatus::VALIDATED);
        $operation->setValidatedBy($this->getUser());
        $operation->setValidatedAt(new \DateTime());

        foreach ($operation->getPalettes() as $palette) {
            if ($palette->getStatus() === PaletteStatus::EN_ATTENTE) {
                $palette->setStatus(PaletteStatus::VALIDEE);
            }
        }

        $this->em->flush();
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

        $reason = $request->request->get('reason', '');
        if (empty($reason)) {
            $this->addFlash('error', 'Le motif de rejet est obligatoire.');
            return $this->redirectToRoute('app_operation_show', ['id' => $operation->getId()]);
        }

        $operation->setStatus(StockStatus::REJECTED);
        $operation->setRejectionReason($reason);
        $this->em->flush();

        $this->addFlash('success', 'Opération rejetée.');
        return $this->redirectToRoute('app_operation_entry_index');
    }

    // ===== FICHE DE DÉCHARGE =====

    #[Route('/{id}/generer-fiche', name: 'app_operation_generate_fiche', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_CONTROLEUR')]
    public function generateFiche(Operation $operation, Request $request, UserRepository $userRepo): Response
    {
        if ($operation->getFicheDecharge()) {
            // Assurer la synchro aussi dans le cas où la fiche existe déjà.
            // On ré-aligne toujours Operation.controleur avec la fiche.
            $ficheExistante = $operation->getFicheDecharge();
            if ($ficheExistante && $ficheExistante->getControleur() && $ficheExistante->getControleur() !== $operation->getControleur()) {
                $operation->setControleur($ficheExistante->getControleur());
                $this->em->persist($operation);
                $this->em->flush();
            }

            return $this->redirectToRoute('app_fiche_show', ['id' => $ficheExistante->getId()]);
        }

        if ($request->isMethod('POST')) {
            $controleurId = $request->request->get('controleur');
            $controleur = $controleurId ? $userRepo->find($controleurId) : $this->getUser();

            $fiche = new FicheDecharge();
            $fiche->setNumero(FicheDecharge::generateNumero());
            $fiche->setOperation($operation);
            $fiche->setControleur($controleur);
            $fiche->setStatus(FicheStatus::EN_COURS_CONTROLE);

            // Synchroniser l'opération avec l'assignation du contrôleur sur la fiche.
            // Sinon, la page "Opérations d'Entrée" (filtrée sur Operation.controleur) ne remonte rien.
            $operation->setControleur($controleur);

            $this->em->persist($fiche);
            $this->em->persist($operation);
            $this->em->flush();

            $this->addFlash('success', 'Fiche de décharge générée et assignée.');
            return $this->redirectToRoute('app_fiche_show', ['id' => $fiche->getId()]);
        }

        return $this->render('operation/generate_fiche.html.twig', [
            'operation' => $operation,
            'controleurs' => $userRepo->findByRole('ROLE_CONTROLEUR'),
        ]);
    }

    // ===== BON D'ENTRÉE =====

    #[Route('/{id}/bon-entree', name: 'app_operation_bon_entree', methods: ['GET'])]
    #[IsGranted('ROLE_CONTROLEUR')]
    public function bonEntree(Operation $operation): Response
    {
        if (!$operation->isValidated()) {
            $this->addFlash('error', 'Le bon d\'entrée ne peut être généré qu\'après validation complète.');
            return $this->redirectToRoute('app_operation_show', ['id' => $operation->getId()]);
        }

        return $this->render('operation/bon_entree.html.twig', [
            'operation' => $operation,
        ]);
    }

    // ===== API ENDPOINTS =====

    #[Route('/api/rayons/{coldRoomId}', name: 'app_operation_api_rayons', methods: ['GET'])]
    public function apiRayons(int $coldRoomId): JsonResponse
    {
        $rayons = [];
        for ($i = 1; $i <= 44; $i++) {
            $rayons[] = 'R' . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
        }
        return $this->json($rayons);
    }

    // ===== SORTIES =====

    #[Route('/sorties', name: 'app_operation_exit_index')]
    #[IsGranted('ROLE_CONTROLEUR')]
    public function exitIndex(): Response
    {
        $user = $this->getUser();
        if ($this->isGranted('ROLE_CHEF_STOCK')) {
            $operations = $this->operationRepo->findByType(OperationType::EXIT);
        } else {
            $operations = $this->operationRepo->findByControleur($user, OperationType::EXIT);
        }

        return $this->render('operation/exit_index.html.twig', [
            'operations' => $operations,
        ]);
    }

    #[Route('/sorties/preparer', name: 'app_operation_exit_prepare', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_CHEF_STOCK')]
    public function exitPrepare(
        Request $request,
        ClientRepository $clientRepo,
        UserRepository $userRepo,
    ): Response {
        $clientId = $request->query->get('client') ?? $request->request->get('client');
        $selectedClient = $clientId ? $clientRepo->find($clientId) : null;

        $palettes = [];
        $filters = [];
        if ($selectedClient) {
            $filters = [
                'espece' => $request->query->get('espece'),
                'qualite' => $request->query->get('qualite'),
                'moule' => $request->query->get('moule'),
                'famille' => $request->query->get('famille'),
                'coldRoom' => $request->query->get('cold_room'),
                'rayon' => $request->query->get('rayon'),
            ];
            $palettes = $this->paletteRepo->findAvailableByClient($selectedClient, array_filter($filters));
        }

        // Handle form submission - create exit operation
        if ($request->isMethod('POST') && $request->request->has('selected_palettes')) {
            $selectedIds = $request->request->all('selected_palettes');
            $controleurId = $request->request->get('controleur');

            if (empty($selectedIds)) {
                $this->addFlash('error', 'Sélectionnez au moins une palette.');
                return $this->redirectToRoute('app_operation_exit_prepare', ['client' => $clientId]);
            }

            $operation = new Operation();
            $operation->setCode(Operation::generateCode(OperationType::EXIT));
            $operation->setType(OperationType::EXIT);
            $operation->setClient($selectedClient);
            $operation->setCreatedBy($this->getUser());

            if ($controleurId) {
                $operation->setControleur($userRepo->find($controleurId));
            }

            $this->em->persist($operation);

            // Link selected palettes to exit operation (create exit palette references)
            $index = 1;
            foreach ($selectedIds as $paletteId) {
                $sourcePalette = $this->paletteRepo->find($paletteId);
                if ($sourcePalette && $sourcePalette->isStockAvailable()) {
                    // Créer une nouvelle palette de sortie avec un code unique (sinon UNIQUE constraint sur palettes.code_palette)
                    $exitPalette = new Palette();
                    $exitPalette->setCodePalette(Palette::generateCode($operation->getCode(), (int)$index));
                    $exitPalette->setEspece($sourcePalette->getEspece());
                    $exitPalette->setFamille($sourcePalette->getFamille());
                    $exitPalette->setQualite($sourcePalette->getQualite());
                    $exitPalette->setMoule($sourcePalette->getMoule());
                    $exitPalette->setNombreCartons($sourcePalette->getCartonsRestants());
                    $exitPalette->setPoidsCarton($sourcePalette->getPoidsCarton());
                    $exitPalette->setColdRoom($sourcePalette->getColdRoom());
                    $exitPalette->setRayon($sourcePalette->getRayon());
                    $operation->addPalette($exitPalette);
                    $this->em->persist($exitPalette);
                    $index++;
                }
            }

            // Générer automatiquement la fiche pour la sortie (fiche de charge)
            if ($operation->getControleur()) {
                $fiche = new FicheDecharge();
                $fiche->setNumero(FicheDecharge::generateNumero());
                $fiche->setOperation($operation);
                $fiche->setControleur($operation->getControleur());
                $fiche->setStatus(FicheStatus::EN_COURS_CONTROLE);
                $this->em->persist($fiche);
            }

            $this->em->flush();

            $this->addFlash('success', 'Opération de sortie créée : ' . $operation->getCode());
            return $this->redirectToRoute('app_operation_show', ['id' => $operation->getId()]);
        }

        return $this->render('operation/exit_prepare.html.twig', [
            'clients' => $clientRepo->findBy(['isActive' => true], ['companyName' => 'ASC']),
            'selectedClient' => $selectedClient,
            'palettes' => $palettes,
            'controleurs' => $userRepo->findByRole('ROLE_CONTROLEUR'),
            'filters' => $filters,
        ]);
    }

    #[Route('/{id}/bon-sortie', name: 'app_operation_bon_sortie', methods: ['GET'])]
    #[IsGranted('ROLE_CONTROLEUR')]
    public function bonSortie(Operation $operation): Response
    {
        if (!$operation->isValidated()) {
            $this->addFlash('error', 'Le bon de sortie ne peut être généré qu\'après validation.');
            return $this->redirectToRoute('app_operation_show', ['id' => $operation->getId()]);
        }

        return $this->render('operation/bon_sortie.html.twig', [
            'operation' => $operation,
        ]);
    }
}
