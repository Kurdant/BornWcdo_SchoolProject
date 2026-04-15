<?php

namespace WCDO\Repositories;

use PDO;
use WCDO\Config\Database;
use WCDO\Entities\Categorie;

class CategorieRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->prepare('SELECT id, nom, image FROM CATEGORIE ORDER BY nom ASC');
        $stmt->execute();
        
        $categories = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $categories[] = new Categorie(
                id: $row['id'],
                nom: $row['nom'],
                image: $row['image']
            );
        }
        
        return $categories;
    }

    public function findById(int $id): ?Categorie
    {
        $stmt = $this->pdo->prepare('SELECT id, nom, image FROM CATEGORIE WHERE id = :id');
        $stmt->execute(['id' => $id]);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            return null;
        }
        
        return new Categorie(
            id: $row['id'],
            nom: $row['nom'],
            image: $row['image']
        );
    }

    public function create(string $nom): Categorie
    {
        $stmt = $this->pdo->prepare('INSERT INTO CATEGORIE (nom) VALUES (:nom)');
        $stmt->execute(['nom' => $nom]);
        
        return new Categorie(
            id: (int)$this->pdo->lastInsertId(),
            nom: $nom
        );
    }
}
