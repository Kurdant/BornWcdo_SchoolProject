<?php

namespace WCDO\Repositories;

use PDO;

use WCDO\Entities\PanierLigne;

use WCDO\Repositories\BaseRepository;

class PanierProduitRepository extends BaseRepository
{

    /**
     * Récupère toutes les lignes d'un panier
     */
    public function findByPanierId(int $panierId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, id_panier, id_produit, quantite, prix_unitaire, details 
             FROM PANIER_PRODUIT 
             WHERE id_panier = :id_panier
             ORDER BY id ASC'
        );
        $stmt->execute(['id_panier' => $panierId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $lignes = [];
        foreach ($rows as $row) {
            $lignes[] = $this->hydratePanierLigne($row);
        }
        return $lignes;
    }

    /**
     * Ajoute une ligne au panier
     */
    public function add(
        int $panierId,
        int $produitId,
        int $quantite,
        float $prixUnitaire,
        array $details
    ): PanierLigne {
        $stmt = $this->pdo->prepare(
            'INSERT INTO PANIER_PRODUIT (id_panier, id_produit, quantite, prix_unitaire, details)
             VALUES (:id_panier, :id_produit, :quantite, :prix_unitaire, :details)'
        );

        $stmt->execute([
            'id_panier' => $panierId,
            'id_produit' => $produitId,
            'quantite' => $quantite,
            'prix_unitaire' => $prixUnitaire,
            'details' => json_encode($details)
        ]);

        $id = (int) $this->pdo->lastInsertId();
        return new PanierLigne(
            id: $id,
            idPanier: $panierId,
            idProduit: $produitId,
            quantite: $quantite,
            prixUnitaire: $prixUnitaire,
            details: $details
        );
    }

    /**
     * Supprime une ligne du panier par son ID
     */
    public function deleteById(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM PANIER_PRODUIT WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Supprime toutes les lignes d'un panier (RG-006)
     */
    public function deleteByPanierId(int $panierId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM PANIER_PRODUIT WHERE id_panier = :id_panier');
        return $stmt->execute(['id_panier' => $panierId]);
    }

    /**
     * Transforme une ligne BDD en objet PanierLigne
     */
    private function hydratePanierLigne(array $row): PanierLigne
    {
        return new PanierLigne(
            id: (int) $row['id'],
            idPanier: (int) $row['id_panier'],
            idProduit: (int) $row['id_produit'],
            quantite: (int) $row['quantite'],
            prixUnitaire: (float) $row['prix_unitaire'],
            details: json_decode($row['details'], true) ?? []
        );
    }
}
