<?php

namespace App\Controller;

use App\Repository\ClientRepository;
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

        $reply = $this->generateReply($message);
        return $this->json(['reply' => $reply]);
    }

    private function generateReply(string $message): string
    {
        // Greetings
        if (preg_match('/^(bonjour|salut|hello|hi|hey|salam)/', $message)) {
            return "Bonjour ! 👋 Je suis l'assistant Golden Logistics. Comment puis-je vous aider ?\n\nVoici ce que je peux faire :\n• Infos sur les clients\n• État du stock\n• Statistiques des opérations\n• Aide sur l'utilisation du système";
        }

        // Client questions
        if (str_contains($message, 'client') || str_contains($message, 'combien de client')) {
            $total = count($this->clientRepo->findAll());
            $actifs = count($this->clientRepo->findBy(['isActive' => true]));
            return "📊 **Clients :**\n• Total : {$total}\n• Actifs : {$actifs}\n• Inactifs : " . ($total - $actifs);
        }

        // Stock questions
        if (str_contains($message, 'stock') || str_contains($message, 'palette') || str_contains($message, 'combien')) {
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
            return "📦 **État du stock :**\n• Palettes totales : {$total}\n• Palettes en stock : {$enStock}\n• Poids total en stock : " . number_format($poids, 0, ',', ' ') . " kg (" . number_format($poids/1000, 2) . " T)";
        }

        // Operations
        if (str_contains($message, 'opération') || str_contains($message, 'operation') || str_contains($message, 'entrée') || str_contains($message, 'sortie')) {
            $ops = $this->operationRepo->findAll();
            $entries = 0; $exits = 0; $pending = 0;
            foreach ($ops as $op) {
                if ($op->getType()->value === 'entry') $entries++;
                else $exits++;
                if ($op->isPending()) $pending++;
            }
            return "📋 **Opérations :**\n• Entrées : {$entries}\n• Sorties : {$exits}\n• En attente de validation : {$pending}";
        }

        // Help
        if (str_contains($message, 'aide') || str_contains($message, 'help') || str_contains($message, 'comment')) {
            return "🔍 **Guide rapide :**\n\n• **Nouvelle entrée** : Menu Entrées → Nouvelle Entrée\n• **Préparer sortie** : Menu Sorties → Préparer une sortie\n• **Voir stock** : Menu État du stock\n• **Transférer palette** : Menu Transferts (contrôleur)\n• **Générer facture** : Menu Factures → Générer\n\nPosez-moi une question sur les clients, le stock ou les opérations !";
        }

        // Merci
        if (str_contains($message, 'merci') || str_contains($message, 'thanks')) {
            return "De rien ! 😊 N'hésitez pas si vous avez d'autres questions.";
        }

        // Default
        return "🤔 Je ne suis pas sûr de comprendre. Essayez de me demander :\n• \"Combien de clients ?\"\n• \"État du stock\"\n• \"Opérations en cours\"\n• \"Aide\" pour le guide d'utilisation";
    }
}
