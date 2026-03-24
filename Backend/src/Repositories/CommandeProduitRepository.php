<?php

namespace WCDO\Repositories;

use PDO;
use WCDO\Config\Database;
use WCDO\Entities\PanierLigne;

class CommandeProduitRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Ajoute une ligne de commande à partir d'une ligne de panier
     * 
     * QUAND ? Dans CommandeService::creer(), pour chaque ligne du panier :
     *   foreach ($panier->lignes() as $ligne) {
     *       $commandeProduitRepo->addFromPanierLigne($commande->id(), $ligne);
     *   }
     * 
     * IMPORTANT : Cette méthode est appelée APRÈS create() de la commande et DANS la transaction SQL.
     * Si une ligne échoue (erreur BDD), toute la transaction rollback → atomicité garantie.
     * 
     * @param int $commandeId L'ID de la commande fraîchement créée
     * @param PanierLigne $ligne La ligne à insérer (réutilise l'Entité PanierLigne)
     */
    public function addFromPanierLigne(int $commandeId, PanierLigne $ligne): void
    {
        // INSERT : copie les données du panier vers la commande
        // Les données ne changeront JAMAIS après création (RG-010)
        $stmt = $this->pdo->prepare(
            'INSERT INTO COMMANDE_PRODUIT 
             (id_commande, id_produit, quantite, prix_unitaire, details)
             VALUES (:id_commande, :id_produit, :quantite, :prix_unitaire, :details)'
        );

        // Exécute avec les données de la ligne
        $stmt->execute([
            'id_commande' => $commandeId,
            'id_produit' => $ligne->idProduit(),
            'quantite' => $ligne->quantite(),
            'prix_unitaire' => $ligne->prixUnitaire(),
            'details' => json_encode($ligne->details())  // JSON flexible : sauces, taille, etc.
        ]);
        
        // Cette méthode ne retourne rien (void) : elle fait juste l'insertion
        // Le Service vérife qu'aucune exception n'est levée
    }

    /**
     * Récupère toutes les lignes d'une commande
     * 
     * Cas d'usage : Un client reçoit un numéro de chevalet, clique "Voir ma commande".
     * Le Controller appelle : CommandeController::getProduits(numero_commande)
     *   → cherche la commande via CommandeRepository
     *   → appelle findByCommandeId($commande->id())
     *   → retourne les lignes avec prix payé (immuable depuis l'achat)
     * 
     * IMPORTANT : Les prix ici sont ceux payés (prix_unitaire), pas les prix courants (PRODUIT.prix).
     * Si le burger coûtait 8€ quand je l'ai acheté et coûte 9€ maintenant,
     * mon historique doit afficher 8€ (traçabilité comptable).
     * 
     * @param int $commandeId L'ID de la commande
     * @return array Tableau d'objets PanierLigne
     */
    public function findByCommandeId(int $commandeId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, id_commande, id_produit, quantite, prix_unitaire, details
             FROM COMMANDE_PRODUIT
             WHERE id_commande = :id_commande
             ORDER BY id ASC'
        );
        $stmt->execute(['id_commande' => $commandeId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Hydrate chaque ligne en objet PanierLigne (réutilise la même Entité)
        $lignes = [];
        foreach ($rows as $row) {
            $lignes[] = $this->hydratePanierLigne($row);
        }
        return $lignes;
    }

    /**
     * Supprime toutes les lignes d'une commande
     * 
     * RAREMENT UTILISÉE en production.
     * Cas où ça arrive : admin supprime une commande erronée depuis le tableau de bord.
     * 
     * La cascade DELETE sur id_commande FK fera ça automatiquement,
     * mais on la propose pour explicitude du code Service.
     * 
     * @param int $commandeId L'ID de la commande à nettoyer
     */
    public function deleteByCommandeId(int $commandeId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM COMMANDE_PRODUIT WHERE id_commande = :id_commande');
        return $stmt->execute(['id_commande' => $commandeId]);
    }

    /**
     * Transforme une ligne BDD en objet PanierLigne
     * 
     * POURQUOI RÉUTILISER PanierLigne ET PAS CRÉER CommandeLigne ?
     * - Sémantiquement identique : un produit + quantité + détails + prix
     * - Pas besoin de deux classes quasi-identiques (violation DRY)
     * - PHP strict typing : PanierLigne est typée, c'est ce qu'on retourne
     * 
     * @param array $row Ligne depuis PDO::FETCH_ASSOC
     * @return PanierLigne Objet hydraté
     */
    private function hydratePanierLigne(array $row): PanierLigne
    {
        return new PanierLigne(
            id: (int) $row['id'],
            idPanier: (int) $row['id_commande'],  // Sémantiquement = id_commande ici
            idProduit: (int) $row['id_produit'],
            quantite: (int) $row['quantite'],
            prixUnitaire: (float) $row['prix_unitaire'],  // Cast string → float
            details: json_decode($row['details'], true) ?? []  // JSON → tableau PHP
        );
    }
}
