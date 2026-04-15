<?php

declare(strict_types=1);

namespace WCDO\Repositories;

use PDO;
use WCDO\Config\Database;
use WCDO\Entities\Admin;

class AdminRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    public function findById(int $id): ?Admin
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, nom, email, mot_de_passe, role FROM ADMIN WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrateAdmin($row) : null;
    }

    public function findByEmail(string $email): ?Admin
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, nom, email, mot_de_passe, role FROM ADMIN WHERE email = :email'
        );
        $stmt->execute(['email' => $email]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrateAdmin($row) : null;
    }

    /**
     * Retourne tous les admins triés par nom croissant.
     *
     * @return Admin[]
     */
    public function findAll(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, nom, email, mot_de_passe, role FROM ADMIN ORDER BY nom ASC'
        );
        $stmt->execute();

        $admins = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $admins[] = $this->hydrateAdmin($row);
        }

        return $admins;
    }

    /**
     * Crée un admin avec le rôle spécifié (défaut : administration).
     */
    public function create(
        string $nom,
        string $email,
        string $motDePasseHash,
        string $role = Admin::ROLE_ADMINISTRATION
    ): Admin {
        $stmt = $this->pdo->prepare(
            'INSERT INTO ADMIN (nom, email, mot_de_passe, role) VALUES (:nom, :email, :mot_de_passe, :role)'
        );
        $stmt->execute([
            'nom'          => $nom,
            'email'        => $email,
            'mot_de_passe' => $motDePasseHash,
            'role'         => $role,
        ]);

        return new Admin(
            id:         (int) $this->pdo->lastInsertId(),
            nom:        $nom,
            email:      $email,
            motDePasse: $motDePasseHash,
            role:       $role,
        );
    }

    /**
     * Met à jour les informations d'un admin.
     * Le mot de passe n'est modifié que si $motDePasseHash est fourni (non null).
     *
     * @throws \InvalidArgumentException Si le rôle n'est pas valide.
     */
    public function update(
        int $id,
        string $nom,
        string $email,
        string $role,
        ?string $motDePasseHash = null
    ): bool {
        // Valider le rôle avant toute requête SQL
        $rolesValides = [Admin::ROLE_ADMINISTRATION, Admin::ROLE_PREPARATION, Admin::ROLE_ACCUEIL];
        if (!in_array($role, $rolesValides, true)) {
            throw new \InvalidArgumentException("Rôle invalide : '$role'.");
        }

        // Construction conditionnelle du SET selon que le mot de passe change ou non
        if ($motDePasseHash !== null) {
            $sql = 'UPDATE ADMIN SET nom = :nom, email = :email, role = :role, mot_de_passe = :mot_de_passe WHERE id = :id';
            $params = [
                'nom'          => $nom,
                'email'        => $email,
                'role'         => $role,
                'mot_de_passe' => $motDePasseHash,
                'id'           => $id,
            ];
        } else {
            $sql = 'UPDATE ADMIN SET nom = :nom, email = :email, role = :role WHERE id = :id';
            $params = [
                'nom'   => $nom,
                'email' => $email,
                'role'  => $role,
                'id'    => $id,
            ];
        }

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute($params);
    }

    /**
     * Supprime un admin par son ID.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM ADMIN WHERE id = :id');

        return $stmt->execute(['id' => $id]);
    }

    private function hydrateAdmin(array $row): Admin
    {
        return new Admin(
            id:         (int) $row['id'],
            nom:        $row['nom'],
            email:      $row['email'],
            motDePasse: $row['mot_de_passe'],
            role:       $row['role'],
        );
    }
}
