<?php

namespace App\Controller;

use App\Enum\OperationType;
use App\Enum\PaletteStatus;
use App\Enum\StockStatus;
use App\Repository\ClientRepository;
use App\Repository\ColdRoomRepository;
use App\Repository\OperationRepository;
use App\Repository\PaletteRepository;
use Doctrine\ORM\EntityManagerInterface;
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
        private EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'app_chatbot')]
    public function index(): Response
    {
        return $this->render('chatbot/index.html.twig');
    }

    #[Route('/ask', name: 'app_chatbot_ask', methods: ['POST'])]
    public function ask(Request $request): JsonResponse
    {
        $raw = trim($request->request->get('message', ''));
        if (empty($raw)) {
            return $this->json(['reply' => 'Veuillez poser une question.']);
        }
        return $this->json(['reply' => $this->generateReply($raw)]);
    }

    // ─── Normalise → lowercase ASCII sans accents ──────────────────────────────
    private function n(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        return str_replace(
            ['é','è','ê','ë','à','â','ä','î','ï','ô','ö','ù','û','ü','ç','ñ','æ','œ'],
            ['e','e','e','e','a','a','a','i','i','o','o','u','u','u','c','n','ae','oe'],
            $s
        );
    }

    // ─── Router principal ──────────────────────────────────────────────────────
    private function generateReply(string $raw): string
    {
        $n = $this->n($raw); // version normalisée, sans accents, minuscules

        // Salutations
        if (preg_match('/^(bonjour|bonsoir|salut|hello|hi|hey|salam|coucou|yo)/', $n)) {
            /** @var \App\Entity\User|null $user */
            $user = $this->getUser();
            $name = $user ? ' ' . $user->getFirstName() : '';
            return "Bonjour{$name} ! 👋 Je suis l'assistant Golden Logistics.\n\nJe peux vous aider à :\n📊 Consulter les données (stock, chambres, palettes…)\n📖 Vous guider dans l'application (créer une entrée, valider, facturer…)\n\nExemples :\n• \"comment créer une entrée\"\n• \"état du stock\"\n• \"occupation chambres\"\n• \"aide\" pour voir toutes les commandes";
        }

        // Remerciements
        if (preg_match('/^(merci|thank|super|parfait|ok merci|tres bien|nickel|genial)/', $n)) {
            return "De rien ! 😊 N'hésitez pas si vous avez d'autres questions.";
        }

        // ── Guides de navigation (testé avant les requêtes de données) ────────
        if ($this->isNavQuestion($n)) {
            return $this->handleNav($n);
        }

        // ── Requêtes de données ────────────────────────────────────────────────

        // Palette spécifique  ex: "palette P-2024-001"
        if (preg_match('/palette\s+([A-Za-z0-9\-_]+)/i', $n, $m)) {
            return $this->searchPalette($m[1]);
        }

        // Code opération  ex: "BR-2024-001" ou "BS 2024-001"
        if (preg_match('/(br|bs)[- ][a-z0-9\-]+/i', $n, $m)) {
            return $this->searchOperation(preg_replace('/\s+/', '-', $m[0]));
        }

        // Stock d'un client spécifique
        if (preg_match('/stock\s+(?:du\s+client\s+|client\s+|de\s+)(.+)/i', $n, $m)) {
            $name = trim($m[1]);
            if ($name) {
                return $this->getClientStock($name);
            }
        }

        // Chambre froide spécifique  ex: "chambre F1"
        if (preg_match('/chambre\s+(?:froide\s+)?([a-z][a-z0-9]*)/i', $n, $m)) {
            return $this->getColdRoomInfo($n);
        }
        // Chambres froides en général
        if ($this->has($n, ['chambre', 'occupation', 'rempli', 'frigo', 'capacit', 'temperature', 'froid'])) {
            return $this->getColdRoomInfo($n);
        }

        // Opérations en attente
        if ($this->has($n, ['attente', 'pending', 'valider', 'non valide', 'a valider'])) {
            return $this->getPendingOperations();
        }

        // Dernière opération — avec type optionnel (entrée / sortie)
        if ($this->has($n, ['derniere operation', 'derniere entree', 'derniere sortie', 'last operation', 'derniere op'])) {
            return $this->getLastOperation($this->detectOpType($n));
        }
        if (str_contains($n, 'derniere') && $this->has($n, ['oper', 'entree', 'sortie', 'livraison'])) {
            return $this->getLastOperation($this->detectOpType($n));
        }

        // Nombre de palettes
        if (($this->has($n, ['combien', 'nombre', 'total'])) && str_contains($n, 'palet')) {
            $count = $this->paletteRepo->countActivePalettes();
            return "📦 Palettes actuellement en stock : {$count}";
        }

        // Clients — uniquement pour les questions purement statistiques (combien, liste, nombre...)
        // Les questions "créer/ajouter/nouveau client" sont déjà traitées par isNavQuestion() plus haut
        if (str_contains($n, 'client') && !str_contains($n, 'stock')
            && $this->has($n, ['combien','nombre','liste','total','tous','afficher','voir les'])) {
            $total  = count($this->clientRepo->findAll());
            $actifs = count($this->clientRepo->findBy(['isActive' => true]));
            return "👥 Clients :\n• Total : {$total}\n• Actifs : {$actifs}\n• Inactifs : " . ($total - $actifs);
        }

        // Stock par espèce  ex: "stock sardine"
        if (preg_match('/(?:stock|combien\s+de?)\s+([a-z]+)$/i', $n, $m)) {
            $term = trim($m[1]);
            $skip = ['client','clients','palette','palettes','carton','cartons','chambre','chambres','global','du','de','le','la','total','general'];
            if ($term && !in_array($term, $skip, true)) {
                return $this->getStockByEspece($term);
            }
        }

        // Stock global / état
        if ($this->has($n, ['stock', 'global', 'etat', 'resume', 'situation', 'synthese', 'bilan', 'vue'])) {
            return $this->getGlobalStock();
        }

        // Aide
        if ($this->has($n, ['aide', 'help', 'quoi', 'commande', 'que peux', 'que fais', 'capacite', 'fonction'])) {
            return $this->getFullHelp();
        }

        return "🤔 Je ne comprends pas cette question.\n\nEssayez :\n• \"état du stock\"\n• \"occupation chambres\"\n• \"dernière opération\"\n• \"stock client [nom]\"\n• \"comment créer une entrée\"\n• \"aide\" pour voir toutes les options";
    }

    // ─── Helpers de détection ──────────────────────────────────────────────────

    /** Vérifie si la chaîne contient au moins un des mots-clés */
    private function has(string $n, array $keywords): bool
    {
        foreach ($keywords as $kw) {
            if (str_contains($n, $kw)) return true;
        }
        return false;
    }

    // ─── Détection navigation ──────────────────────────────────────────────────

    private function isNavQuestion(string $n): bool
    {
        // Mots exprimant une intention d'action ou une demande de guide
        $intentWords = ['comment','creer','cree','ajouter','ajout','nouveau','nouvelle',
                        'enregistrer','saisir','faire','veux','voudrais','souhaite',
                        'valider','generer','transferer','deplacer','imprimer','telecharger',
                        'gerer','acceder','voir','consulter','demarrer','lancer'];

        $hasIntent = $this->has($n, $intentWords);

        // Si un mot d'intention est présent + un mot-clé de module → c'est une question de navigation
        if ($hasIntent) {
            if ($this->has($n, ['client','fournisseur']))                         return true;
            if ($this->has($n, ['entree','reception','arrivee']))                  return true;
            if ($this->has($n, ['sortie','expedition','retrait']))                 return true;
            if ($this->has($n, ['facture','facturation','facturer']))              return true;
            if ($this->has($n, ['transfert','palette','deplacer']))               return true;
            if ($this->has($n, ['chambre','froide','frigo','frigorifique']))       return true;
            if ($this->has($n, ['alerte','notification']))                         return true;
            if ($this->has($n, ['audit','journal','historique','trace']))          return true;
            if ($this->has($n, ['bon','fiche','imprimer','telecharger','decharge'])) return true;
            if ($this->has($n, ['stock','rayon','plan','emplacement']))            return true;
        }

        // Phrases exactes reconnues indépendamment de l'intention
        $navKw = [
            'plan des rayons','plan rayon','carte rayons',
            'journal audit','historique audit',
            'bon entree','bon sortie','fiche decharge','fiche charge',
        ];
        return $this->has($n, $navKw);
    }

    private function handleNav(string $n): string
    {
        // Priorité : sortie avant client (évite que "sortie" déclenche "client" si les deux sont dans le texte)
        if ($this->has($n, ['sortie','expedition','livraison sortante','retrait','enlever'])) return $this->guideSortie();
        if ($this->has($n, ['entree','reception','livraison entrante','arrivee']))            return $this->guideEntree();
        if ($this->has($n, ['valider','validation','approuver','approbation']))               return $this->guideValidation();
        if ($this->has($n, ['facture','facturation','facturer','generer','pdf']))             return $this->guideFacture();
        if ($this->has($n, ['transfert','transferer','deplacer']))                            return $this->guideTransfert();
        if ($this->has($n, ['audit','journal','historique','log','trace']))                   return $this->guideAudit();
        if ($this->has($n, ['chambre','froide','frigorifique','frigo']))                      return $this->guideChambreFroide();
        if ($this->has($n, ['alerte','notification','alarme']))                               return $this->guideAlertes();
        if ($this->has($n, ['bon','imprimer','telecharger']))                                 return $this->guideBonImpression();
        if ($this->has($n, ['fiche','decharge']))                                             return $this->guideBonImpression();
        if ($this->has($n, ['plan','rayon','rayons','shelf','emplacement']))                  return $this->guidePlanRayons();
        if ($this->has($n, ['client','fournisseur']))                                         return $this->guideClient();
        if ($this->has($n, ['stock','consulter','voir','acceder']))                           return $this->guideConsulterStock();
        return $this->getGuideMenu();
    }

    // ─── Guides pas à pas ─────────────────────────────────────────────────────

    private function guideEntree(): string
    {
        if ($this->isGranted('ROLE_CLIENT')) {
            return "ℹ️ En tant que client, vous ne pouvez pas créer d'entrées vous-même.\nContactez le personnel de l'entrepôt pour enregistrer une réception.";
        }
        return "📥 Comment créer une entrée de stock :\n\n1️⃣ Menu gauche → cliquez sur \"Entrées\"\n2️⃣ Cliquez sur \"Nouvelle entrée\"\n3️⃣ Remplissez le formulaire :\n   • Sélectionnez le client\n   • Renseignez la date de réception\n   • Choisissez la chambre froide\n   • Ajoutez les palettes (espèce, qualité, cartons, poids, rayon)\n4️⃣ Cliquez sur \"Enregistrer\"\n\n✅ L'opération passe en statut \"En attente de contrôle\".\nUn contrôleur devra ensuite valider le bon d'entrée sur le terrain.\n\n💡 Tapez \"comment valider\" pour la suite du processus.";
    }

    private function guideSortie(): string
    {
        if ($this->isGranted('ROLE_CLIENT')) {
            return "📤 En tant que client, pour demander une sortie :\n\n1️⃣ Contactez le chef de stock ou contrôleur\n2️⃣ Précisez : espèce, quantité de cartons, rayon\n\nLe personnel créera la sortie dans l'application.";
        }
        return "📤 Comment créer une sortie de stock :\n\n1️⃣ Menu gauche → cliquez sur \"Sorties\"\n2️⃣ Cliquez sur \"Nouvelle sortie\"\n3️⃣ Remplissez le formulaire :\n   • Sélectionnez le client\n   • Choisissez les palettes à sortir (depuis le stock disponible)\n   • Indiquez la quantité de cartons à prélever\n4️⃣ Cliquez sur \"Préparer la sortie\"\n\n⚠️ Vérifiez les quantités avant de valider.\nLa sortie déduit automatiquement le stock une fois validée.";
    }

    private function guideValidation(): string
    {
        if ($this->isGranted('ROLE_CONTROLEUR') && !$this->isGranted('ROLE_CHEF_STOCK')) {
            return "🔍 Votre rôle (contrôleur) vous permet de :\n\n1️⃣ Aller dans \"Entrées\" ou \"Sorties\"\n2️⃣ Cliquer sur une opération \"En attente de contrôle\"\n3️⃣ Vérifier les informations (palettes, poids, emplacement) sur le terrain\n4️⃣ Valider le contrôle terrain\n\nℹ️ La validation finale est effectuée par le chef de stock.";
        }
        if (!($this->isGranted('ROLE_CHEF_STOCK') || $this->isGranted('ROLE_DIRECTEUR') || $this->isGranted('ROLE_PATRON'))) {
            return "ℹ️ La validation des opérations nécessite un rôle chef de stock ou supérieur.";
        }
        return "✅ Comment valider une opération :\n\n1️⃣ Menu gauche → \"Entrées\" ou \"Sorties\"\n2️⃣ Les opérations en attente ont un badge orange\n3️⃣ Cliquez sur le code de l'opération pour ouvrir le détail\n4️⃣ Vérifiez les informations (palettes, poids, chambre, rayon)\n5️⃣ Cliquez sur \"Valider\" pour confirmer\n\n✔️ Une fois validée :\n• Le stock est mis à jour immédiatement\n• La fiche de charge est générée\n• L'opération est enregistrée dans l'audit\n\n🔔 La cloche en haut à droite signale les opérations en attente.";
    }

    private function guideFacture(): string
    {
        if ($this->isGranted('ROLE_CLIENT')) {
            return "🧾 Comment consulter vos factures :\n\n1️⃣ Menu gauche → \"Mes factures\"\n2️⃣ La liste de vos factures s'affiche avec leur statut\n3️⃣ Cliquez sur une facture pour le détail\n4️⃣ Cliquez sur \"Télécharger\" pour obtenir le PDF";
        }
        if (!($this->isGranted('ROLE_CHEF_STOCK') || $this->isGranted('ROLE_DIRECTEUR') || $this->isGranted('ROLE_PATRON'))) {
            return "ℹ️ La génération de factures est réservée au chef de stock et à la direction.";
        }
        return "🧾 Comment générer une facture :\n\n1️⃣ Menu gauche → \"Factures\"\n2️⃣ Cliquez sur \"Nouvelle facture\"\n3️⃣ Sélectionnez le client\n4️⃣ Choisissez la période ou les opérations à facturer\n5️⃣ Vérifiez les lignes (sorties, poids, tarifs)\n6️⃣ Cliquez sur \"Générer\"\n\n📄 La facture est disponible en PDF.\n💡 Vous pouvez l'imprimer depuis la page de détail.";
    }

    private function guideClient(): string
    {
        if (!($this->isGranted('ROLE_CHEF_STOCK') || $this->isGranted('ROLE_DIRECTEUR') || $this->isGranted('ROLE_PATRON'))) {
            return "ℹ️ La gestion des clients est réservée au chef de stock et à la direction.";
        }
        return "👥 Comment ajouter un client :\n\n1️⃣ Menu gauche, section \"Gestion\" → \"Clients\"\n2️⃣ Cliquez sur \"Nouveau client\"\n3️⃣ Remplissez les informations :\n   • Raison sociale (nom de l'entreprise)\n   • Code client\n   • Coordonnées (téléphone, email, adresse)\n4️⃣ Cliquez sur \"Enregistrer\"\n\n✅ Le client est disponible lors de la création d'entrées et sorties.\n💡 Pour modifier : cliquez sur son nom dans la liste puis \"Modifier\".";
    }

    private function guideTransfert(): string
    {
        if (!($this->isGranted('ROLE_CONTROLEUR') || $this->isGranted('ROLE_CHEF_STOCK'))) {
            return "ℹ️ Les transferts de palettes sont gérés par les contrôleurs et le chef de stock.";
        }
        return "🔄 Comment transférer une palette :\n\n1️⃣ Menu gauche → \"Transferts\"\n2️⃣ Cliquez sur \"Nouveau transfert\"\n3️⃣ Remplissez le formulaire :\n   • Sélectionnez la palette à déplacer\n   • Choisissez la nouvelle chambre froide de destination\n   • Indiquez le nouveau rayon (ex: G12, D5)\n4️⃣ Confirmez le transfert\n\n✅ Le stock est déplacé vers le nouvel emplacement.\nL'opération est tracée dans le journal d'audit.";
    }

    private function guidePlanRayons(): string
    {
        if (!($this->isGranted('ROLE_CONTROLEUR') || $this->isGranted('ROLE_CHEF_STOCK') || $this->isGranted('ROLE_DIRECTEUR') || $this->isGranted('ROLE_PATRON'))) {
            return "ℹ️ Le plan des rayons est accessible aux contrôleurs et au personnel d'encadrement.";
        }
        return "🗂️ Comment utiliser le plan des rayons :\n\n1️⃣ Menu gauche → \"État du stock\"\n   OU ouvrez une chambre froide et cliquez sur un rayon\n2️⃣ La grille 22×2 s'affiche (G1–G22 gauche / D1–D22 droite) :\n   • 🟢 Vert = rayon libre\n   • 🟡 Jaune = partiellement occupé\n   • 🔴 Rouge = occupé\n3️⃣ Cliquez sur un rayon pour voir le détail :\n   • Palettes présentes, client, espèce, cartons, poids\n   • Filtres par client et par espèce\n\n💡 Dans la fiche d'une chambre froide, cliquez sur une case pour afficher le stock de ce rayon directement.";
    }

    private function guideChambreFroide(): string
    {
        if (!($this->isGranted('ROLE_CHEF_STOCK') || $this->isGranted('ROLE_DIRECTEUR') || $this->isGranted('ROLE_PATRON'))) {
            return "ℹ️ La gestion des chambres froides est réservée au chef de stock et à la direction.";
        }
        return "🏭 Comment gérer les chambres froides :\n\n📋 Voir les chambres :\n1️⃣ Menu gauche → \"Chambres froides\"\n2️⃣ Liste avec taux d'occupation, capacité et température\n3️⃣ Cliquez sur une chambre pour voir le plan détaillé\n\n➕ Ajouter une chambre :\n1️⃣ Cliquez sur \"Nouvelle chambre froide\"\n2️⃣ Renseignez : nom, capacité (en tonnes), température cible\n3️⃣ Enregistrez\n\n✏️ Modifier / Désactiver :\nCliquez sur les icônes d'action dans la liste.\n\n⚠️ Une chambre ne peut pas être désactivée si elle contient du stock.";
    }

    private function guideAlertes(): string
    {
        if (!($this->isGranted('ROLE_CHEF_STOCK') || $this->isGranted('ROLE_DIRECTEUR') || $this->isGranted('ROLE_PATRON'))) {
            return "ℹ️ Le centre d'alertes est accessible au chef de stock et à la direction.";
        }
        return "🔔 Comment consulter les alertes :\n\n1️⃣ Cliquez sur la cloche 🔔 en haut à droite\n   OU menu gauche, section \"Système\" → \"Alertes\"\n\nTypes d'alertes :\n• 🔴 Chambre froide saturée (≥ 90% de capacité)\n• 🟠 Chambre froide presque pleine (≥ 70%)\n• ⚠️ Stock critique client (stock < 20% initial)\n• ⏳ Opérations en attente de validation\n\n💡 Un bandeau rouge s'affiche en haut du tableau de bord pour les alertes critiques.";
    }

    private function guideAudit(): string
    {
        if ($this->isGranted('ROLE_CONTROLEUR') && !$this->isGranted('ROLE_CHEF_STOCK')) {
            return "📖 Votre historique de contrôle :\n\n1️⃣ Menu gauche → \"Mon historique\"\n2️⃣ Toutes vos opérations de contrôle sont listées avec les dates et résultats.";
        }
        if (!($this->isGranted('ROLE_CHEF_STOCK') || $this->isGranted('ROLE_DIRECTEUR') || $this->isGranted('ROLE_PATRON'))) {
            return "ℹ️ Le journal d'audit est réservé au chef de stock et à la direction.";
        }
        return "📖 Comment utiliser le journal d'audit :\n\n1️⃣ Menu gauche, section \"Système\" → \"Journal d'audit\"\n2️⃣ Toutes les actions importantes sont tracées automatiquement :\n   • Créations et modifications d'opérations\n   • Validations et rejets\n   • Transferts de palettes\n   • Connexions utilisateurs\n3️⃣ Utilisez les filtres (date, utilisateur, type d'action) pour affiner la recherche\n\n✅ L'audit est automatique — aucune action manuelle nécessaire.";
    }

    private function guideBonImpression(): string
    {
        return "🖨️ Comment imprimer / télécharger un bon :\n\n📥 Bon d'entrée :\n1️⃣ Menu gauche → \"Entrées\"\n2️⃣ Cliquez sur l'opération concernée\n3️⃣ Cliquez sur \"Imprimer bon d'entrée\" ou l'icône 🖨️\n\n📤 Bon de sortie :\n1️⃣ Menu gauche → \"Sorties\"\n2️⃣ Cliquez sur l'opération concernée\n3️⃣ Cliquez sur \"Imprimer bon de sortie\"\n\n📋 Fiche de décharge :\n• Disponible depuis le détail de l'opération validée\n\n💡 Depuis n'importe quelle page de détail, utilisez le bouton \"Imprimer\" ou Ctrl+P.";
    }

    private function guideConsulterStock(): string
    {
        if ($this->isGranted('ROLE_CLIENT')) {
            return "📦 Comment consulter votre stock :\n\n1️⃣ Menu gauche → \"Mon stock\"\n2️⃣ Filtrez par espèce, qualité, rayon ou chambre\n3️⃣ Cliquez sur une palette pour le détail complet\n\n📊 Vous verrez :\n• Cartons restants vs cartons initiaux\n• Poids restant\n• Localisation (chambre froide + rayon)\n• Historique des mouvements";
        }
        return "📦 Comment consulter l'état du stock :\n\n1️⃣ Menu gauche → \"État du stock\"\n2️⃣ Vue globale de toutes les palettes en stock\n3️⃣ Filtres disponibles :\n   • Client, espèce, qualité, moule, famille\n   • Chambre froide, rayon, date d'entrée\n4️⃣ Cliquez sur une palette pour le détail\n\n🗂️ Pour une vue graphique → \"Plan des rayons\"\n(Cliquez sur un rayon pour voir les palettes qu'il contient)";
    }

    private function getGuideMenu(): string
    {
        return "📖 Guide d'utilisation — choisissez un sujet :\n\n📥 \"comment créer une entrée\" — Enregistrer une réception\n📤 \"comment créer une sortie\" — Préparer une expédition\n✅ \"comment valider\" — Valider une opération\n🧾 \"comment générer une facture\" — Facturation\n👥 \"comment ajouter un client\" — Gestion des clients\n🔄 \"comment transférer une palette\" — Déplacer du stock\n🗂️ \"plan des rayons\" — Visualisation des emplacements\n🏭 \"gérer les chambres froides\" — Gestion des espaces\n🔔 \"comment voir les alertes\" — Centre d'alertes\n📖 \"journal d'audit\" — Traçabilité des actions\n🖨️ \"imprimer un bon\" — Bons d'entrée / sortie";
    }

    private function getFullHelp(): string
    {
        return "🔍 Toutes les commandes disponibles :\n\n📊 DONNÉES EN TEMPS RÉEL\n• \"état du stock\" → Résumé global\n• \"stock client [nom]\" → Stock d'un client\n• \"stock sardine\" → Stock par espèce\n• \"palette P001\" → Localiser une palette\n• \"BR-2024-001\" → Détail d'une opération\n• \"occupation chambres\" → Taux d'occupation\n• \"chambre F1\" → Détail d'une chambre\n• \"sorties en attente\" → Opérations en attente\n• \"dernière opération\" → Dernière op. validée\n• \"combien de palettes\" → Nombre de palettes\n• \"combien de clients\" → Nombre de clients\n\n📖 GUIDES PAS À PAS\n• \"comment créer une entrée\"\n• \"comment créer une sortie\"\n• \"comment valider une opération\"\n• \"comment générer une facture\"\n• \"comment ajouter un client\"\n• \"comment transférer une palette\"\n• \"plan des rayons\"\n• \"gérer les chambres froides\"\n• \"comment voir les alertes\"\n• \"journal d'audit\"\n• \"imprimer un bon\"";
    }

    // ─── Handlers de données ───────────────────────────────────────────────────

    private function searchPalette(string $code): string
    {
        $palette = $this->paletteRepo->findOneBy(['codePalette' => strtoupper($code)]);
        if (!$palette) {
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
            . "• Qualité : " . ($palette->getQualite() ?? '—') . "\n"
            . "• Cartons restants : {$palette->getCartonsRestants()} / {$palette->getNombreCartons()}\n"
            . "• Poids restant : " . number_format((float) $palette->getPoidsRestant(), 0, ',', ' ') . " kg\n"
            . "• Localisation : " . ($palette->getColdRoom() ? $palette->getColdRoom()->getName() : '—') . " / " . ($palette->getRayon() ?? '—') . "\n"
            . "• Statut : {$status}";
    }

    private function getClientStock(string $nameNormalized): string
    {
        // Cherche dans les noms de clients (sans accents des deux côtés)
        $all = $this->clientRepo->findAll();
        $client = null;
        foreach ($all as $c) {
            if (str_contains($this->n($c->getCompanyName()), $nameNormalized) ||
                str_contains($this->n($c->getCode() ?? ''), $nameNormalized)) {
                $client = $c;
                break;
            }
        }
        if (!$client) {
            return "❌ Client \"{$nameNormalized}\" introuvable.";
        }

        $stockKg  = $this->paletteRepo->getTotalStockByClient($client);
        $palettes = $this->paletteRepo->findStockByClient($client);
        $enStock  = count(array_filter($palettes, fn($p) => $p->getCartonsRestants() > 0));
        $especes  = array_unique(array_map(fn($p) => $p->getEspece(), $palettes));
        sort($especes);

        $reply = "📊 Stock de {$client->getCompanyName()} :\n"
            . "• Palettes en stock : {$enStock}\n"
            . "• Poids total : " . number_format($stockKg, 0, ',', ' ') . " kg"
            . " (" . number_format($stockKg / 1000, 2) . " T)\n";

        if (!empty($especes)) {
            $reply .= "• Espèces : " . implode(', ', $especes);
        }

        return $reply;
    }

    private function getColdRoomInfo(string $n): string
    {
        $rooms = $this->coldRoomRepo->findActive();

        // Chambre spécifique ?
        if (preg_match('/chambre\s+(?:froide\s+)?([a-z][a-z0-9]*)/i', $n, $m)) {
            $search = trim($m[1]);
            foreach ($rooms as $room) {
                if (str_contains($this->n($room->getName()), $search)) {
                    $rate  = $room->getOccupancyRate();
                    $used  = $room->getUsedCapacity();
                    $avail = $room->getAvailableCapacity();
                    $icon  = $rate >= 90 ? '🔴' : ($rate >= 70 ? '🟠' : '🟢');
                    return "{$icon} {$room->getName()} :\n"
                        . "• Taux d'occupation : " . number_format($rate, 1) . "%\n"
                        . "• Capacité max : " . $room->getMaxCapacityTons() . " T\n"
                        . "• Utilisé : " . number_format($used, 2) . " T\n"
                        . "• Disponible : " . number_format($avail, 2) . " T";
                }
            }
        }

        if (empty($rooms)) {
            return "Aucune chambre froide active trouvée.";
        }

        usort($rooms, fn($a, $b) => $b->getOccupancyRate() <=> $a->getOccupancyRate());
        $lines = ["🏭 Taux d'occupation des chambres froides :\n"];
        foreach ($rooms as $room) {
            $rate  = $room->getOccupancyRate();
            $used  = $room->getUsedCapacity();
            $max   = $room->getMaxCapacityTons();
            $icon  = $rate >= 90 ? '🔴' : ($rate >= 70 ? '🟠' : '🟢');
            $lines[] = "{$icon} {$room->getName()} : " . number_format($rate, 1) . "% (" . number_format($used, 1) . " / {$max} T)";
        }
        return implode("\n", $lines);
    }

    private function getPendingOperations(): string
    {
        $pendingStatuses = [
            StockStatus::PENDING->value,
            StockStatus::EN_ATTENTE_CONTROLE->value,
            StockStatus::EN_ATTENTE_VALIDATION->value,
        ];

        $ops = $this->operationRepo->createQueryBuilder('o')
            ->where('o.status IN (:statuses)')
            ->setParameter('statuses', $pendingStatuses)
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults(5)
            ->getQuery()->getResult();

        $totalCount = (int) $this->operationRepo->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.status IN (:statuses)')
            ->setParameter('statuses', $pendingStatuses)
            ->getQuery()->getSingleScalarResult();

        if ($totalCount === 0) {
            return "✅ Aucune opération en attente de validation.";
        }

        $lines = ["⏳ Opérations en attente ({$totalCount}) :\n"];
        foreach ($ops as $op) {
            $type    = $op->getType() === OperationType::ENTRY ? '📥 Entrée' : '📤 Sortie';
            $lines[] = "{$type} — {$op->getCode()} — {$op->getClient()->getCompanyName()} ({$op->getCreatedAt()->format('d/m')}) — " . $op->getStatus()->label();
        }
        if ($totalCount > 5) {
            $lines[] = "… et " . ($totalCount - 5) . " autre(s)";
        }
        $lines[] = "\n💡 Tapez \"comment valider\" pour savoir comment procéder.";
        return implode("\n", $lines);
    }

    /** Extrait le type demandé (ENTRY / EXIT / null = tous) depuis la chaîne normalisée */
    private function detectOpType(string $n): ?OperationType
    {
        if ($this->has($n, ['entree', 'entrees', 'reception', 'arrivee'])) return OperationType::ENTRY;
        if ($this->has($n, ['sortie', 'sorties', 'expedition', 'depart'])) return OperationType::EXIT;
        return null;
    }

    private function getLastOperation(?OperationType $filterType = null): string
    {
        $criteria = ['status' => StockStatus::VALIDATED];
        if ($filterType !== null) {
            $criteria['type'] = $filterType;
        }

        $ops = $this->operationRepo->findBy($criteria, ['validatedAt' => 'DESC'], 1);

        if (empty($ops)) {
            $suffix = match($filterType) {
                OperationType::ENTRY => " d'entrée",
                OperationType::EXIT  => " de sortie",
                default              => '',
            };
            return "Aucune opération{$suffix} validée trouvée.";
        }

        $op      = $ops[0];
        $typeLabel = $op->getType() === OperationType::ENTRY ? 'Entrée' : 'Sortie';
        $header  = $filterType !== null
            ? "📋 Dernière {$op->getType()->label()} validée"
            : "📋 Dernière opération validée";

        return "{$header} :\n"
            . "• Code : {$op->getCode()}\n"
            . "• Type : {$typeLabel}\n"
            . "• Client : {$op->getClient()->getCompanyName()}\n"
            . "• Palettes : {$op->getNombrePalettes()}\n"
            . "• Date : " . ($op->getValidatedAt()?->format('d/m/Y H:i') ?? '—');
    }

    private function searchOperation(string $code): string
    {
        $op = $this->operationRepo->findOneBy(['code' => strtoupper($code)]);
        if (!$op) {
            return "❌ Opération \"{$code}\" introuvable.";
        }
        $type = $op->getType() === OperationType::ENTRY ? 'Entrée' : 'Sortie';
        return "📋 Opération {$op->getCode()} :\n"
            . "• Type : {$type}\n"
            . "• Client : {$op->getClient()->getCompanyName()}\n"
            . "• Statut : {$op->getStatus()->label()}\n"
            . "• Palettes : {$op->getNombrePalettes()}\n"
            . "• Poids : " . number_format($op->getPoidsTotal(), 0, ',', ' ') . " kg";
    }

    private function getGlobalStock(): string
    {
        $conn     = $this->em->getConnection();
        $excluded = [PaletteStatus::SORTIE_COMPLETE->value, PaletteStatus::REJETEE->value];

        $row = $conn->fetchAssociative(
            'SELECT COUNT(p.id) as palettes, SUM(p.cartons_restants) as cartons, SUM(p.poids_restant) as poids_kg
             FROM palettes p
             INNER JOIN operations o ON p.operation_id = o.id
             WHERE o.status = :validated AND o.type = :type
               AND p.cartons_restants > 0
               AND p.status NOT IN (:excl1, :excl2)',
            [
                'validated' => StockStatus::VALIDATED->value,
                'type'      => OperationType::ENTRY->value,
                'excl1'     => $excluded[0],
                'excl2'     => $excluded[1],
            ]
        );

        $palettes      = (int)   ($row['palettes'] ?? 0);
        $cartons       = (int)   ($row['cartons']  ?? 0);
        $poidsKg       = (float) ($row['poids_kg'] ?? 0);
        $activeRooms   = count($this->coldRoomRepo->findActive());
        $activeClients = count($this->clientRepo->findBy(['isActive' => true]));

        return "📦 État du stock global :\n"
            . "• Palettes en stock : {$palettes}\n"
            . "• Cartons restants : " . number_format($cartons, 0, ',', ' ') . "\n"
            . "• Poids total : " . number_format($poidsKg, 0, ',', ' ') . " kg (" . number_format($poidsKg / 1000, 2) . " T)\n"
            . "• Chambres froides actives : {$activeRooms}\n"
            . "• Clients actifs : {$activeClients}";
    }

    private function getStockByEspece(string $espece): string
    {
        $conn = $this->em->getConnection();
        $rows = $conn->fetchAllAssociative(
            'SELECT p.espece, COUNT(p.id) as palettes, SUM(p.cartons_restants) as cartons, SUM(p.poids_restant) as poids_kg
             FROM palettes p
             INNER JOIN operations o ON p.operation_id = o.id
             WHERE o.status = :validated AND o.type = :type
               AND p.cartons_restants > 0
               AND p.status NOT IN (:excl1, :excl2)
               AND LOWER(p.espece) LIKE :espece
             GROUP BY p.espece
             ORDER BY cartons DESC',
            [
                'validated' => StockStatus::VALIDATED->value,
                'type'      => OperationType::ENTRY->value,
                'excl1'     => PaletteStatus::SORTIE_COMPLETE->value,
                'excl2'     => PaletteStatus::REJETEE->value,
                'espece'    => '%' . $espece . '%',
            ]
        );

        if (empty($rows)) {
            return "❌ Aucun stock trouvé pour l'espèce \"{$espece}\".";
        }

        $lines = ["🐟 Stock — espèce \"{$espece}\" :\n"];
        foreach ($rows as $r) {
            $lines[] = "• {$r['espece']} : {$r['palettes']} palette(s), "
                . number_format((int) $r['cartons'], 0, ',', ' ') . " cartons, "
                . number_format((float) $r['poids_kg'] / 1000, 2) . " T";
        }
        return implode("\n", $lines);
    }
}