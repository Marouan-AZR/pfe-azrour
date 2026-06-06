<?php

namespace App\Controller;

use App\Repository\ClientRepository;
use App\Repository\ColdRoomRepository;
use App\Repository\OperationRepository;
use App\Repository\PaletteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/chatbot')]
#[IsGranted('ROLE_USER')]
class ChatbotController extends AbstractController
{
    public function __construct(
        private ClientRepository $clientRepo,
        private OperationRepository $operationRepo,
        private PaletteRepository $paletteRepo,
        private ColdRoomRepository $coldRoomRepo,
    ) {}

    #[Route('', name: 'app_chatbot')]
    public function index(): Response
    {
        return $this->render('chatbot/index.html.twig');
    }

    #[Route('/ask', name: 'app_chatbot_ask', methods: ['POST'])]
    public function ask(Request $request): JsonResponse
    {
        $message = strtolower(trim($request->request->get('message', '')));
        if (empty($message)) {
            return $this->json(['reply' => 'Veuillez poser une question.']);
        }
        return $this->json(['reply' => $this->generateReply($message)]);
    }

    private function generateReply(string $message): string
    {
        // Greetings
        if (preg_match('/^(bonjour|salut|hello|hi|hey|salam)/', $message)) {
            return "Bonjour ! 👋 Je suis l'assistant Golden Logistics.\n\nJe peux vous aider avec :\n• Stock d'un client (ex: \"stock client MFQE\")\n• Localiser une palette (ex: \"palette P001\")\n• Chambres froides (ex: \"occupation chambres\")\n• Opérations (ex: \"sorties en attente\")\n• Aide générale";
        }

        // Specific palette search
        if (preg_match('/palette\s+([A-Za-z0-9\-]+)/i', $message, $m)) {
            return $this->searchPalette($m[1]);
        }

        // Specific client stock
        if (preg_match('/stock\s+(?:client\s+)?(.+)/i', $message, $m)) {
            $name = trim($m[1]);
            if ($name && $name !== 'du' && $name !== 'de') {
                return $this->getClientStock($name);
            }
        }

        // Cold rooms
        if (str_contains($message, 'chambre') || str_contains($message, 'occupation') || str_contains($message, 'rempli') || str_contains($message, 'plein')) {
            return $this->getColdRoomInfo($message);
        }

        // Operations
        if (str_contains($message, 'attente') || str_contains($message, 'pending')) {
            return $this->getPendingOperations();
        }

        if (str_contains($message, 'dernière opération') || str_contains($message, 'derniere operation')) {
            return $this->getLastOperation();
        }

        if (preg_match('/(BR|BS)-[A-Z0-9\-]+/i', $message, $m)) {
            return $this->searchOperation($m[0]);
        }

        // General stock
        if (str_contains($message, 'stock') || str_contains($message, 'combien')) {
            return $this->getGlobalStock();
        }

        // Client info
        if (str_contains($message, 'client')) {
            $total = count($this->clientRepo->findAll());
            $actifs = count($this->clientRepo->findBy(['isActive' => true]));
            return "📊 Clients :\n• Total : {$total}\n• Actifs : {$actifs}\n• Inactifs : " . ($total - $actifs);
        }

        // Help
        if (str_contains($message, 'aide') || str_contains($message, 'help')) {
            return "🔍 Guide rapide :\n\n• \"stock client [nom]\" → Stock d'un client\n• \"palette [code]\" → Localiser une palette\n• \"occupation chambres\" → Taux d'occupation\n• \"sorties en attente\" → Opérations en attente\n• \"dernière opération\" → Dernière opération validée";
        }

        if (str_contains($message, 'merci')) {
            return "De rien ! 😊 N'hésitez pas si vous avez d'autres questions.";
        }

        return "🤔 Je ne suis pas sûr de comprendre. Essayez :\n• \"stock client [nom]\"\n• \"palette [code]\"\n• \"occupation chambres\"\n• \"aide\" pour plus d'options";
    }

    private function searchPalette(string $code): string
    {
        $palette = $this->paletteRepo->findOneBy(['codePalette' => strtoupper($code)]);
        if (!$palette) {
            // Try partial match
            $palettes = $this->paletteRepo->createQueryBuilder('p')
                ->where('p.codePalette LIKE :code')
                ->setParameter('code', '%' . $code . '%')
                ->setMaxResults(3)
                ->getQuery()->getResult();
            if (empty($palettes)) {
                return "❌ Palette \"{$code}\" introuvable.";
            }
            $palette = $palettes[0];
        }

        $status = $palette->getCartonsRestants() > 0 ? '🟢 En stock' : '🔴 Sortie complète';
        return "📦 Palette {$palette->getCodePalette()} :\n"
            . "• Client : {$palette->getOperation()->getClient()->getCompanyName()}\n"
            . "• Espèce : {$palette->getEspece()}\n"
            . "• Cartons restants : {$palette->getCartonsRestants()}/{$palette->getNombreCartons()}\n"
            . "• Poids restant : " . number_format((float)$palette->getPoidsRestant() * 1000, 0, ',', ' ') . " kg\n"
            . "• Localisation : " . ($palette->getColdRoom() ? $palette->getColdRoom()->getName() : '—') . " / " . ($palette->getRayon() ?? '—') . "\n"
            . "• Statut : {$status}";
    }

    private function getClientStock(string $name): string
    {
        $clients = $this->clientRepo->createQueryBuilder('c')
            ->where('LOWER(c.companyName) LIKE :name')
            ->setParameter('name', '%' . strtolower($name) . '%')
            ->setMaxResults(1)
            ->getQuery()->getResult();

        if (empty($clients)) {
            return "❌ Client \"{$name}\" introuvable.";
        }

        $client = $clients[0];
        $stock = $this->paletteRepo->getTotalStockByClient($client);
        $palettes = $this->paletteRepo->findStockByClient($client);
        $enStock = count(array_filter($palettes, fn($p) => $p->getCartonsRestants() > 0));

        return "📊 Stock de {$client->getCompanyName()} :\n"
            . "• Palettes en stock : {$enStock}\n"
            . "• Poids total : " . number_format($stock * 1000, 0, ',', ' ') . " kg (" . number_format($stock, 2) . " T)";
    }

    private function getColdRoomInfo(string $message): string
    {
        $rooms = $this->coldRoomRepo->findActive();
        
        // Specific room
        if (preg_match('/(?:chambre\s+|de\s+)(F\d+|[A-Za-z]+\d*)/i', $message, $m)) {
            foreach ($rooms as $room) {
                if (stripos($room->getName(), $m[1]) !== false) {
                    $rate = $room->getOccupancyRate();
                    return "🏭 {$room->getName()} :\n• Taux d'occupation : " . number_format($rate, 1) . "%\n• Capacité : " . $room->getMaxCapacityTons() . " T\n• Utilisé : " . number_format($room->getUsedCapacity(), 2) . " T\n• Disponible : " . number_format($room->getAvailableCapacity(), 2) . " T";
                }
            }
        }

        // All rooms
        $lines = ["🏭 Taux d'occupation des chambres :\n"];
        usort($rooms, fn($a, $b) => $b->getOccupancyRate() <=> $a->getOccupancyRate());
        foreach ($rooms as $room) {
            $rate = $room->getOccupancyRate();
            $icon = $rate >= 90 ? '🔴' : ($rate >= 70 ? '🟠' : '🟢');
            $lines[] = "{$icon} {$room->getName()} : " . number_format($rate, 1) . "% (" . number_format($room->getUsedCapacity(), 1) . "/" . $room->getMaxCapacityTons() . " T)";
        }
        return implode("\n", $lines);
    }

    private function getPendingOperations(): string
    {
        $ops = $this->operationRepo->findBy(['status' => 'pending'], ['createdAt' => 'DESC'], 5);
        if (empty($ops)) {
            return "✅ Aucune opération en attente de validation.";
        }
        $lines = ["⏳ Opérations en attente ({$this->operationRepo->count(['status' => 'pending'])}) :\n"];
        foreach ($ops as $op) {
            $type = $op->getType()->value === 'entry' ? '📥' : '📤';
            $lines[] = "{$type} {$op->getCode()} - {$op->getClient()->getCompanyName()} ({$op->getCreatedAt()->format('d/m')})";
        }
        return implode("\n", $lines);
    }

    private function getLastOperation(): string
    {
        $ops = $this->operationRepo->findBy(['status' => 'validated'], ['validatedAt' => 'DESC'], 1);
        if (empty($ops)) {
            return "Aucune opération validée trouvée.";
        }
        $op = $ops[0];
        $type = $op->getType()->value === 'entry' ? 'Entrée' : 'Sortie';
        return "📋 Dernière opération validée :\n• Code : {$op->getCode()}\n• Type : {$type}\n• Client : {$op->getClient()->getCompanyName()}\n• Palettes : {$op->getNombrePalettes()}\n• Date : {$op->getValidatedAt()->format('d/m/Y H:i')}";
    }

    private function searchOperation(string $code): string
    {
        $op = $this->operationRepo->findOneBy(['code' => strtoupper($code)]);
        if (!$op) {
            return "❌ Opération \"{$code}\" introuvable.";
        }
        $type = $op->getType()->value === 'entry' ? 'Entrée' : 'Sortie';
        return "📋 Opération {$op->getCode()} :\n• Type : {$type}\n• Client : {$op->getClient()->getCompanyName()}\n• Statut : {$op->getStatus()->label()}\n• Palettes : {$op->getNombrePalettes()}\n• Poids : " . number_format($op->getPoidsTotal(), 0, ',', ' ') . " kg";
    }

    private function getGlobalStock(): string
    {
        $palettes = $this->paletteRepo->findAll();
        $total = count($palettes);
        $enStock = 0;
        $poids = 0;
        foreach ($palettes as $p) {
            if ($p->getCartonsRestants() > 0) {
                $enStock++;
                $poids += (float)$p->getPoidsRestant();
            }
        }
        return "📦 État du stock global :\n• Palettes totales : {$total}\n• Palettes en stock : {$enStock}\n• Poids total : " . number_format($poids * 1000, 0, ',', ' ') . " kg (" . number_format($poids, 2) . " T)";
    }
}
