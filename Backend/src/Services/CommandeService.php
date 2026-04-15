<?php

declare(strict_types=1);

namespace WCDO\Services;

use InvalidArgumentException;
use PDO;
use WCDO\Config\Database;
use WCDO\Entities\Commande;
use WCDO\Entities\PanierLigne;
use WCDO\Exceptions\StockInsuffisantException;
use WCDO\Repositories\ClientRepository;
use WCDO\Repositories\CommandeProduitRepository;
use WCDO\Repositories\CommandeRepository;
use WCDO\Repositories\PanierProduitRepository;
use WCDO\Repositories\PanierRepository;
use WCDO\Repositories\ProduitRepository;

class CommandeService
{
    private PDO $pdo;
    private PanierRepository $panierRepo;
    private PanierProduitRepository $panierProduitRepo;
    private ProduitRepository $produitRepo;
    private CommandeRepository $commandeRepo;
    private CommandeProduitRepository $commandeProduitRepo;
    private ClientRepository $clientRepo;

    public function __construct()
    {
        // On récupère la connexion PDO directement pour gérer la transaction
        $this->pdo                 = Database::getInstance();
        $this->panierRepo          = new PanierRepository();
        $this->panierProduitRepo   = new PanierProduitRepository();
        $this->produitRepo         = new ProduitRepository();
        $this->commandeRepo        = new CommandeRepository();
        $this->commandeProduitRepo = new CommandeProduitRepository();
        $this->clientRepo          = new ClientRepository();
    }

    /**
     * Transforme le panier en commande — MÉTHODE PRINCIPALE
     *
     * RG-007 : Commande créée UNIQUEMENT après paiement validé
     * RG-008 : Toutes les opérations dans une TRANSACTION SQL (atomicité)
     *
     * Flux :
     *   1. Charger panier + lignes
     *   2. Vérifier stock de chaque produit
     *   3. Calculer montant total
     *   4. BEGIN TRANSACTION
     *   5.   Insérer COMMANDE
     *   6.   Copier lignes → COMMANDE_PRODUIT
     *   7.   Décrémenter stocks
     *   8.   Attribuer points fidélité (si client connecté)
     *   9.   Supprimer le panier
     *   10. COMMIT (ou ROLLBACK si erreur)
     */
    public function creer(
        string $sessionId,
        string $modePaiement,
        string $typeCommande,
        ?int $clientId = null
    ): array {
        // --- ÉTAPE 1 : Charger le panier ---
        $panier = $this->panierRepo->findBySessionId($sessionId);
        if ($panier === null) {
            throw new InvalidArgumentException('Aucun panier trouvé pour cette session.');
        }

        $lignes = $this->panierProduitRepo->findByPanierId($panier->getId());
        if (empty($lignes)) {
            throw new InvalidArgumentException('Le panier est vide.');
        }

        // --- ÉTAPE 2 : Vérifier le stock AVANT la transaction ---
        foreach ($lignes as $ligne) {
            $produit = $this->produitRepo->findById($ligne->getIdProduit());

            if ($produit === null) {
                throw new InvalidArgumentException(
                    "Produit #{$ligne->getIdProduit()} introuvable."
                );
            }

            if ($produit->getStock() < $ligne->getQuantite()) {
                // RG-001 + RG-008 : stock insuffisant → on refuse TOUT
                throw new StockInsuffisantException(
                    $ligne->getIdProduit(),
                    $ligne->getQuantite(),
                    $produit->getStock()
                );
            }
        }

        // --- ÉTAPE 3 : Calculer le montant total ---
        $montantTotal = $this->calculerTotal($lignes);

        // --- ÉTAPE 4 : Générer numéro commande + chevalet ---
        $numeroCommande = $this->genererNumeroCommande();
        $numeroChevalet = random_int(1, 999); // RG-004 : entre 001 et 999

        // --- ÉTAPE 5 : Préparer l'objet Commande ---
        $commande = new Commande(
            id: 0, // AUTO_INCREMENT en BDD
            numeroCommande: $numeroCommande,
            numeroChevalet: $numeroChevalet,
            typeCommande: $typeCommande,
            modePaiement: $modePaiement,
            montantTotal: $montantTotal,
            dateCreation: date('Y-m-d H:i:s'),
            clientId: $clientId // RG-009 : null si anonyme
        );

        // --- ÉTAPE 6 : TRANSACTION SQL (RG-008 : atomicité) ---
        $this->pdo->beginTransaction();

        try {
            // 6a. Insérer la commande en BDD
            $commandeCreee = $this->commandeRepo->create($commande);

            // 6b. Copier chaque ligne du panier vers COMMANDE_PRODUIT
            foreach ($lignes as $ligne) {
                $this->commandeProduitRepo->addFromPanierLigne(
                    $commandeCreee->getId(),
                    $ligne
                );
            }

            // 6c. Décrémenter le stock de chaque produit commandé
            foreach ($lignes as $ligne) {
                $this->produitRepo->updateStock(
                    $ligne->getIdProduit(),
                    $ligne->getQuantite()
                );
            }

            // 6d. Attribuer les points fidélité (RG-005 + RG-009)
            if ($clientId !== null) {
                $points = $commandeCreee->calculerPointsFidelite();
                if ($points > 0) {
                    $this->clientRepo->addFidelityPoints($clientId, $points);
                }
            }

            // 6e. Supprimer le panier et ses lignes (RG-006)
            $this->panierProduitRepo->deleteByPanierId($panier->getId());
            $this->panierRepo->delete($panier->getId());

            // COMMIT — toutes les opérations ont réussi
            $this->pdo->commit();

            return [
                'commande' => $commandeCreee->toArray(),
                'lignes'   => array_map(fn(PanierLigne $l) => $l->toArray(), $lignes),
            ];

        } catch (\Throwable $e) {
            // ROLLBACK — annule TOUTES les modifications depuis beginTransaction()
            $this->pdo->rollBack();
            throw $e; // Remonte l'exception au Controller
        }
    }

    /**
     * Récupère une commande par son numéro avec ses lignes produits
     */
    public function getByNumero(string $numero): ?array
    {
        $commande = $this->commandeRepo->findByNumero($numero);
        if ($commande === null) {
            return null;
        }

        $lignes = $this->commandeProduitRepo->findByCommandeId($commande->getId());

        return [
            'commande' => $commande->toArray(),
            'lignes'   => array_map(fn(PanierLigne $l) => $l->toArray(), $lignes),
        ];
    }

    /**
     * Récupère toutes les commandes (pour l'admin)
     */
    public function getAll(): array
    {
        $commandes = $this->commandeRepo->findAll();
        return array_map(fn(Commande $c) => $c->toArray(), $commandes);
    }

    /**
     * Calcule le montant total du panier
     */
    private function calculerTotal(array $lignes): float
    {
        return array_reduce(
            $lignes,
            fn(float $carry, PanierLigne $ligne) => $carry + $ligne->getSousTotal(),
            0.0
        );
    }

    /**
     * Génère un numéro de commande unique
     * Format : CMD-YYYYMMDD-XXXXX (ex: CMD-20260324-A7B3F)
     */
    private function genererNumeroCommande(): string
    {
        return 'CMD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
    }
}
