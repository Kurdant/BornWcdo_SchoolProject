<?php

namespace WCDO\Repositories;

use WCDO\Entities\Sauce;

use WCDO\Repositories\BaseRepository;

class SauceRepository extends BaseRepository
{

    public function findAll(): array
    {
        $stmt = $this->pdo->prepare('SELECT id, nom, image FROM SAUCE ORDER BY nom ASC');
        $stmt->execute();
        
        $sauces = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $sauces[] = new Sauce(
                id: $row['id'],
                nom: $row['nom'],
                image: $row['image']
            );
        }
        
        return $sauces;
    }

    public function findById(int $id): ?Sauce
    {
        $stmt = $this->pdo->prepare('SELECT id, nom, image FROM SAUCE WHERE id = :id');
        $stmt->execute(['id' => $id]);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            return null;
        }
        
        return new Sauce(
            id: $row['id'],
            nom: $row['nom'],
            image: $row['image']
        );
    }

    public function create(string $nom): Sauce
    {
        $stmt = $this->pdo->prepare('INSERT INTO SAUCE (nom) VALUES (:nom)');
        $stmt->execute(['nom' => $nom]);
        
        return new Sauce(
            id: (int)$this->pdo->lastInsertId(),
            nom: $nom
        );
    }
}
