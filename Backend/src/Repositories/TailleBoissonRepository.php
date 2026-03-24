<?php

namespace WCDO\Repositories;

use PDO;
use WCDO\Config\Database;
use WCDO\Entities\TailleBoisson;

class TailleBoissonRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->prepare('SELECT id, nom, volume, supplement_prix FROM TAILLE_BOISSON ORDER BY volume ASC');
        $stmt->execute();
        
        $tailles = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $tailles[] = new TailleBoisson(
                id: $row['id'],
                nom: $row['nom'],
                volume: (int)$row['volume'],
                supplementPrix: (float)$row['supplement_prix']
            );
        }
        
        return $tailles;
    }

    public function findById(int $id): ?TailleBoisson
    {
        $stmt = $this->pdo->prepare('SELECT id, nom, volume, supplement_prix FROM TAILLE_BOISSON WHERE id = :id');
        $stmt->execute(['id' => $id]);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            return null;
        }
        
        return new TailleBoisson(
            id: $row['id'],
            nom: $row['nom'],
            volume: (int)$row['volume'],
            supplementPrix: (float)$row['supplement_prix']
        );
    }

    public function create(string $nom, int $volume, float $supplementPrix): TailleBoisson
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO TAILLE_BOISSON (nom, volume, supplement_prix) VALUES (:nom, :volume, :supplement_prix)'
        );
        $stmt->execute([
            'nom' => $nom,
            'volume' => $volume,
            'supplement_prix' => $supplementPrix
        ]);
        
        return new TailleBoisson(
            id: (int)$this->pdo->lastInsertId(),
            nom: $nom,
            volume: $volume,
            supplementPrix: $supplementPrix
        );
    }
}
