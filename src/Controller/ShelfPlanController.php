<?php

namespace App\Controller;

use App\Repository\ClientRepository;
use App\Repository\PaletteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ShelfPlanController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    private function checkAccess(): void
    {
        if (!$this->isGranted('ROLE_CONTROLEUR') && !$this->isGranted('ROLE_DIRECTEUR')) {
            throw $this->createAccessDeniedException();
        }
    }

    #[Route('/plan-rayons', name: 'app_shelf_plan')]
    public function index(PaletteRepository $paletteRepo): Response
    {
        $this->checkAccess();
        $data = $paletteRepo->countPalettesGroupedByRayon();

        $shelves = [];
        foreach (['G', 'D'] as $side) {
            for ($i = 1; $i <= 22; $i++) {
                $key = $side . $i;
                $shelves[$key] = $data[$key] ?? ['total' => 0, 'occupied' => 0];
            }
        }

        $occupied = count(array_filter($shelves, fn($s) => $s['occupied'] > 0));

        return $this->render('shelf_plan/index.html.twig', [
            'shelves'         => $shelves,
            'totalShelves'    => 44,
            'occupiedShelves' => $occupied,
            'availableShelves'=> 44 - $occupied,
        ]);
    }

    #[Route('/plan-rayons/{rayon}/data', name: 'app_shelf_plan_data', methods: ['GET'])]
    public function data(string $rayon, Request $request, PaletteRepository $paletteRepo): JsonResponse
    {
        $this->checkAccess();
        $filters = [];
        if ($coldRoomId = $request->query->getInt('coldRoomId')) {
            $cr = $this->em->find(\App\Entity\ColdRoom::class, $coldRoomId);
            if ($cr) {
                $filters['coldRoom'] = $cr;
            }
        }
        $palettes = $paletteRepo->findByRayon($rayon, $filters);

        $rows = array_map(function ($p) {
            $statusMap = [
                'validee'          => ['label' => 'En stock',        'class' => 'success'],
                'sortie_partielle' => ['label' => 'Sortie partielle','class' => 'warning'],
                'en_attente'       => ['label' => 'En attente',      'class' => 'info'],
                'sortie_complete'  => ['label' => 'Sortie complète', 'class' => 'secondary'],
                'rejetee'          => ['label' => 'Rejetée',         'class' => 'danger'],
            ];
            $sv     = $p->getStatus()->value;
            $status = $statusMap[$sv] ?? ['label' => ucfirst(str_replace('_', ' ', $sv)), 'class' => 'secondary'];

            return [
                'codePalette'     => $p->getCodePalette(),
                'client'          => $p->getOperation()->getClient()->getCompanyName(),
                'espece'          => $p->getEspece(),
                'qualite'         => $p->getQualite() ?? '',
                'moule'           => $p->getMoule() ?? '',
                'famille'         => $p->getFamille() ?? '',
                'cartonsRestants' => $p->getCartonsRestants(),
                'poidsRestant'    => (float) $p->getPoidsRestant(),
                'coldRoom'        => $p->getColdRoom()?->getName() ?? '—',
                'rayon'           => $p->getRayon() ?? '—',
                'dateEntree'      => $p->getCreatedAt()->format('d/m/Y'),
                'statusLabel'     => $status['label'],
                'statusClass'     => $status['class'],
            ];
        }, $palettes);

        return $this->json([
            'rayon'   => $rayon,
            'palettes'=> $rows,
            'total'   => count($rows),
            'weight'  => array_sum(array_column($rows, 'poidsRestant')),
            'cartons' => array_sum(array_column($rows, 'cartonsRestants')),
            'clients' => array_values(array_unique(array_column($rows, 'client'))),
            'especes' => array_values(array_unique(array_column($rows, 'espece'))),
        ]);
    }

    #[Route('/plan-rayons/{rayon}', name: 'app_shelf_plan_detail')]
    public function detail(string $rayon, Request $request, PaletteRepository $paletteRepo, ClientRepository $clientRepo): Response
    {
        $this->checkAccess();
        $filters = [
            'search'  => $request->query->get('search'),
            'client'  => $request->query->get('client'),
            'espece'  => $request->query->get('espece'),
            'qualite' => $request->query->get('qualite'),
        ];

        $palettes     = $paletteRepo->findByRayon($rayon, $filters);
        $totalWeight  = array_sum(array_map(fn($p) => (float) $p->getPoidsRestant(), $palettes));
        $totalCartons = array_sum(array_map(fn($p) => $p->getCartonsRestants(), $palettes));
        $clientIds    = array_unique(array_map(fn($p) => $p->getOperation()->getClient()->getId(), $palettes));
        $filterValues = $paletteRepo->getDistinctFilterValues();

        return $this->render('shelf_plan/detail.html.twig', [
            'rayon'        => $rayon,
            'palettes'     => $palettes,
            'totalWeight'  => $totalWeight,
            'totalCartons' => $totalCartons,
            'clientCount'  => \count($clientIds),
            'clients'      => $clientRepo->findAll(),
            'especes'      => $filterValues['especes'],
            'qualites'     => $filterValues['qualites'],
            'filters'      => $filters,
        ]);
    }
}
