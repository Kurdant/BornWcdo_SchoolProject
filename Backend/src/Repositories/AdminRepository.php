<?php

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
            'SELECT id, nom, email, mot_de_passe FROM ADMIN WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            return null;
        }
        
        return $this->hydrateAdmin($row);
    }

    public function findByEmail(string $email): ?Admin
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, nom, email, mot_de_passe FROM ADMIN WHERE email = :email'
        );
        $stmt->execute(['email' => $email]);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            return null;
        }
        
        return $this->hydrateAdmin($row);
    }

    public function create(string $nom, string $email, string $motDePasseHash): Admin
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO ADMIN (nom, email, mot_de_passe) VALUES (:nom, :email, :mot_de_passe)'
        );
        $stmt->execute([
            'nom' => $nom,
            'email' => $email,
            'mot_de_passe' => $motDePasseHash
        ]);
        
        return new Admin(
            id: (int)$this->pdo->lastInsertId(),
            nom: $nom,
            email: $email,
            motDePasseHash: $motDePasseHash
        );
    }

    private function hydrateAdmin(array $row): Admin
    {
        return new Admin(
            id: (int)$row['id'],
            nom: $row['nom'],
            email: $row['email'],
            motDePasseHash: $row['mot_de_passe']
        );
    }
}
