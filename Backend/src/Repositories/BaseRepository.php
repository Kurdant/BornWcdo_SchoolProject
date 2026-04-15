<?php

declare(strict_types=1);

namespace WCDO\Repositories;

use PDO;
use WCDO\Config\Database;

/**
 * Classe de base pour tous les repositories.
 *
 * Centralise l'injection de la connexion PDO et expose des helpers communs.
 * Tous les repositories héritent de cette classe (principe d'héritage POO).
 */
abstract class BaseRepository
{
    protected PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    /**
     * Exécute une requête préparée et retourne le statement.
     */
    protected function execute(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Retourne le dernier ID auto-incrémenté inséré.
     */
    protected function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }
}
