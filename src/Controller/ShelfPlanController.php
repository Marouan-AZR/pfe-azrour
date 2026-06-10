<?php

namespace App\Controller;

use App\Repository\ClientRepository;
use App\Repository\PaletteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_CONTROLEUR')]
class ShelfPlanController extends AbstractController
{
    #[Route('/plan-rayons', name: 'app_shelf_plan')]
    public function index(PaletteRepository $paletteRepo): Response
    {
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
            'shelves' => $shelves,
            'totalShelves' => 44,
            'occupiedShelves' => $occupied,
            'availableShelves' => 44 - $occupied,
        ]);
    }

    #[Route('/plan-rayons/{rayon}', name: 'app_shelf_plan_detail')]
    public function detail(string $rayon, Request $request, PaletteRepository $paletteRepo, ClientRepository $clientRepo): Response
    {
        $filters = [
            'search' => $request->query->get('search'),
            'client' => $request->query->get('client'),
            'espece' => $request->query->get('espece'),
        ];

        $palettes = $paletteRepo->findByRayon($rayon, $filters);
        $totalWeight = array_sum(array_map(fn($p) => (float)$p->getPoidsRestant(), $palettes));

        return $this->render('shelf_plan/detail.html.twig', [
            'rayon' => $rayon,
            'palettes' => $palettes,
            'totalWeight' => $totalWeight,
            'clients' => $clientRepo->findAll(),
            'especes' => $paletteRepo->getDistinctFilterValues()['especes'],
            'filters' => $filters,
        ]);
    }
}
