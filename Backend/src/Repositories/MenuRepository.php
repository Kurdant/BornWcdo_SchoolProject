<?php

declare(strict_types=1);

namespace WCDO\Repositories;

use PDO;

use WCDO\Entities\Menu;

use WCDO\Repositories\BaseRepository;

class MenuRepository extends BaseRepository
{

    /**
     * Retourne tous les menus, avec leurs produits associés.
     *
     * @param bool $disponibleSeulement Si true, exclut les menus désactivés.
     * @return Menu[]
     */
    public function findAll(bool $disponibleSeulement = false): array
    {
        $sql = 'SELECT id, nom, description, prix, image, disponible, date_creation FROM MENU';

        if ($disponibleSeulement) {
            $sql .= ' WHERE disponible = 1';
        }

        $sql .= ' ORDER BY nom ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        $menus = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $produits = $this->loadProduitsPourMenu((int) $row['id']);
            $menus[]  = $this->hydrateMenu($row, $produits);
        }

        return $menus;
    }

    /**
     * Retourne un menu par son ID, ou null s'il n'existe pas.
     */
    public function findById(int $id): ?Menu
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, nom, description, prix, image, disponible, date_creation
             FROM MENU
             WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $produits = $this->loadProduitsPourMenu((int) $row['id']);

        return $this->hydrateMenu($row, $produits);
    }

    /**
     * Crée un menu et insère sa composition (MENU_PRODUIT) dans une transaction.
     *
     * @param array $produits [['id_produit' => int, 'quantite' => int], ...]
     */
    public function create(
        string $nom,
        string $description,
        float $prix,
        ?string $image,
        array $produits
    ): Menu {
        $this->pdo->beginTransaction();

        try {
            // Insertion du menu principal
            $stmt = $this->pdo->prepare(
                'INSERT INTO MENU (nom, description, prix, image, disponible, date_creation)
                 VALUES (:nom, :description, :prix, :image, 1, NOW())'
            );
            $stmt->execute([
                'nom'         => $nom,
                'description' => $description,
                'prix'        => $prix,
                'image'       => $image,
            ]);

            $idMenu = (int) $this->pdo->lastInsertId();

            // Insertion de chaque ligne de composition
            $this->insererProduits($idMenu, $produits);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return new Menu(
            id:           $idMenu,
            nom:          $nom,
            description:  $description,
            prix:         $prix,
            image:        $image,
            disponible:   true,
            dateCreation: date('Y-m-d H:i:s'),
            produits:     $produits,
        );
    }

    /**
     * Met à jour un menu et remplace entièrement sa composition.
     * DELETE + re-INSERT (plus simple et fiable qu'un diff).
     *
     * @param array $produits [['id_produit' => int, 'quantite' => int], ...]
     */
    public function update(
        int $id,
        string $nom,
        string $description,
        float $prix,
        ?string $image,
        bool $disponible,
        array $produits
    ): bool {
        $this->pdo->beginTransaction();

        try {
            // Mise à jour du menu principal
            $stmt = $this->pdo->prepare(
                'UPDATE MENU
                 SET nom = :nom, description = :description, prix = :prix,
                     image = :image, disponible = :disponible
                 WHERE id = :id'
            );
            $stmt->execute([
                'nom'         => $nom,
                'description' => $description,
                'prix'        => $prix,
                'image'       => $image,
                'disponible'  => $disponible ? 1 : 0,
                'id'          => $id,
            ]);

            // Remplacement total de la composition
            $stmtDelete = $this->pdo->prepare(
                'DELETE FROM MENU_PRODUIT WHERE id_menu = :id_menu'
            );
            $stmtDelete->execute(['id_menu' => $id]);

            $this->insererProduits($id, $produits);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return true;
    }

    /**
     * Supprime un menu (la FK CASCADE supprime automatiquement MENU_PRODUIT).
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM MENU WHERE id = :id');

        return $stmt->execute(['id' => $id]);
    }

    // -------------------------------------------------------------------------
    // Méthodes privées
    // -------------------------------------------------------------------------

    /**
     * Charge la composition d'un menu depuis MENU_PRODUIT.
     *
     * @return array [['id_produit' => int, 'quantite' => int], ...]
     */
    private function loadProduitsPourMenu(int $idMenu): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id_produit, quantite FROM MENU_PRODUIT WHERE id_menu = :id_menu'
        );
        $stmt->execute(['id_menu' => $idMenu]);

        $produits = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $produits[] = [
                'id_produit' => (int) $row['id_produit'],
                'quantite'   => (int) $row['quantite'],
            ];
        }

        return $produits;
    }

    /**
     * Insère les lignes MENU_PRODUIT pour un menu donné.
     * Réutilisé dans create() et update() pour respecter le principe DRY.
     *
     * @param array $produits [['id_produit' => int, 'quantite' => int], ...]
     */
    private function insererProduits(int $idMenu, array $produits): void
    {
        if (empty($produits)) {
            return;
        }

        $stmtProduit = $this->pdo->prepare(
            'INSERT INTO MENU_PRODUIT (id_menu, id_produit, quantite)
             VALUES (:id_menu, :id_produit, :quantite)'
        );

        foreach ($produits as $ligne) {
            $stmtProduit->execute([
                'id_menu'    => $idMenu,
                'id_produit' => (int) $ligne['id_produit'],
                'quantite'   => (int) $ligne['quantite'],
            ]);
        }
    }

    /**
     * Construit un objet Menu à partir d'une ligne BDD et de son tableau de produits.
     */
    private function hydrateMenu(array $row, array $produits): Menu
    {
        return new Menu(
            id:           (int) $row['id'],
            nom:          $row['nom'],
            description:  $row['description'],
            prix:         (float) $row['prix'],
            image:        $row['image'],                   // Nullable
            disponible:   (bool) $row['disponible'],       // TINYINT(1) → bool
            dateCreation: $row['date_creation'],           // String directe depuis la BDD
            produits:     $produits,
        );
    }
}
