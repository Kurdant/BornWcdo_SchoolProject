<?php

namespace WCDO\Repositories;

use PDO;

use WCDO\Entities\Produit;

use WCDO\Repositories\BaseRepository;

class ProduitRepository extends BaseRepository
{

    public function findAll(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, nom, description, prix, stock, id_categorie, image, disponible, date_creation 
             FROM PRODUIT 
             ORDER BY nom ASC'
        );
        $stmt->execute();
        
        $produits = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $produits[] = $this->hydrateProduit($row);
        }
        
        return $produits;
    }

    public function findById(int $id): ?Produit
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, nom, description, prix, stock, id_categorie, image, disponible, date_creation 
             FROM PRODUIT 
             WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            return null;
        }
        
        return $this->hydrateProduit($row);
    }

    public function findByCategorie(int $categorieId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, nom, description, prix, stock, id_categorie, image, disponible, date_creation 
             FROM PRODUIT 
             WHERE id_categorie = :categorie_id 
             ORDER BY nom ASC'
        );
        $stmt->execute(['categorie_id' => $categorieId]);
        
        $produits = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $produits[] = $this->hydrateProduit($row);
        }
        
        return $produits;
    }

    public function create(
        string $nom,
        string $description,
        float $prix,
        int $stock,
        int $categorieId,
        ?string $image = null,
        bool $disponible = true
    ): Produit {
        $stmt = $this->pdo->prepare(
            'INSERT INTO PRODUIT (nom, description, prix, stock, id_categorie, image, disponible, date_creation) 
             VALUES (:nom, :description, :prix, :stock, :categorie_id, :image, :disponible, NOW())'
        );
        $stmt->execute([
            'nom' => $nom,
            'description' => $description,
            'prix' => $prix,
            'stock' => $stock,
            'categorie_id' => $categorieId,
            'image' => $image,
            'disponible' => $disponible ? 1 : 0,
        ]);
        
        return new Produit(
            id: (int)$this->pdo->lastInsertId(),
            nom: $nom,
            description: $description,
            prix: $prix,
            stock: $stock,
            idCategorie: $categorieId,
            image: $image,
            dateCreation: date('Y-m-d H:i:s'),
            disponible: $disponible,
        );
    }

    public function updateStock(int $id, int $quantite): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE PRODUIT SET stock = stock - :quantite WHERE id = :id'
        );

        return $stmt->execute([
            'quantite' => $quantite,
            'id'       => $id,
        ]);
    }

    /**
     * Met à jour toutes les informations d'un produit.
     * L'image peut être null pour conserver l'image existante côté métier
     * (la décision est laissée au Service/Controller).
     */
    public function update(
        int $id,
        string $nom,
        string $description,
        float $prix,
        int $stock,
        int $categorieId,
        ?string $image,
        bool $disponible = true
    ): bool {
        $stmt = $this->pdo->prepare(
            'UPDATE PRODUIT
             SET nom = :nom, description = :description, prix = :prix,
                 stock = :stock, id_categorie = :categorie_id, image = :image,
                 disponible = :disponible
             WHERE id = :id'
        );

        return $stmt->execute([
            'nom'          => $nom,
            'description'  => $description,
            'prix'         => $prix,
            'stock'        => $stock,
            'categorie_id' => $categorieId,
            'image'        => $image,
            'disponible'   => $disponible ? 1 : 0,
            'id'           => $id,
        ]);
    }

    /**
     * Supprime un produit par son ID.
     * Attention : les lignes PANIER_PRODUIT et COMMANDE_PRODUIT référençant ce produit
     * doivent utiliser ON DELETE RESTRICT (FK) pour éviter toute suppression silencieuse.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM PRODUIT WHERE id = :id');

        return $stmt->execute(['id' => $id]);
    }

    private function hydrateProduit(array $row): Produit
    {
        return new Produit(
            id: (int)$row['id'],
            nom: $row['nom'],
            description: $row['description'],
            prix: (float)$row['prix'],
            stock: (int)$row['stock'],
            idCategorie: (int)$row['id_categorie'],
            image: $row['image'],
            dateCreation: $row['date_creation'],
            disponible: isset($row['disponible']) ? (bool)(int)$row['disponible'] : true,
        );
    }
}
