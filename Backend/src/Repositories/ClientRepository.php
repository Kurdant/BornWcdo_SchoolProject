<?php

namespace WCDO\Repositories;

use PDO;
use WCDO\Config\Database;
use WCDO\Entities\Client;

class ClientRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    public function findById(int $id): ?Client
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, prenom, nom, email, mot_de_passe, points_fidelite, date_creation 
             FROM CLIENT 
             WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            return null;
        }
        
        return $this->hydrateClient($row);
    }

    public function findByEmail(string $email): ?Client
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, prenom, nom, email, mot_de_passe, points_fidelite, date_creation 
             FROM CLIENT 
             WHERE email = :email'
        );
        $stmt->execute(['email' => $email]);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            return null;
        }
        
        return $this->hydrateClient($row);
    }

    public function create(string $prenom, string $nom, string $email, string $motDePasseHash): Client
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO CLIENT (prenom, nom, email, mot_de_passe, points_fidelite, date_creation) 
             VALUES (:prenom, :nom, :email, :mot_de_passe, 0, NOW())'
        );
        $stmt->execute([
            'prenom' => $prenom,
            'nom' => $nom,
            'email' => $email,
            'mot_de_passe' => $motDePasseHash
        ]);
        
        return new Client(
            id: (int)$this->pdo->lastInsertId(),
            prenom: $prenom,
            nom: $nom,
            email: $email,
            motDePasse: $motDePasseHash,
            pointsFidelite: 0,
            dateCreation: date('Y-m-d H:i:s')
        );
    }

    public function addFidelityPoints(int $id, int $points): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE CLIENT SET points_fidelite = points_fidelite + :points WHERE id = :id'
        );
        
        return $stmt->execute([
            'points' => $points,
            'id' => $id
        ]);
    }

    private function hydrateClient(array $row): Client
    {
        return new Client(
            id: (int)$row['id'],
            prenom: $row['prenom'],
            nom: $row['nom'],
            email: $row['email'],
            motDePasse: $row['mot_de_passe'],
            pointsFidelite: (int)$row['points_fidelite'],
            dateCreation: $row['date_creation']
        );
    }
}
