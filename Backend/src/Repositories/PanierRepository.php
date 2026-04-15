<?php

declare(strict_types=1);

namespace WCDO\Repositories;

use PDO;

use WCDO\Entities\Panier;

use WCDO\Repositories\BaseRepository;

class PanierRepository extends BaseRepository
{

    /**
     * Récupère le panier d'une session (crée un nouveau s'il n'existe pas)
     * Retourne null si la session n'existe pas ET qu'on ne la crée pas
     */
    public function findOrCreateBySessionId(string $sessionId, ?int $clientId = null): Panier
    {
        $existing = $this->findBySessionId($sessionId);
        if ($existing !== null) {
            return $existing;
        }

        return $this->create($sessionId, $clientId);
    }

    /**
     * Récupère un panier existant par session_id
     */
    public function findBySessionId(string $sessionId): ?Panier
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, session_id, client_id, date_creation, updated_at 
             FROM PANIER 
             WHERE session_id = :session_id'
        );
        $stmt->execute(['session_id' => $sessionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydratePanier($row) : null;
    }

    /**
     * Récupère un panier par son ID
     */
    public function findById(int $id): ?Panier
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, session_id, client_id, date_creation, updated_at 
             FROM PANIER 
             WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydratePanier($row) : null;
    }

    /**
     * Crée un nouveau panier pour une session
     * clientId NULL = visiteur anonyme (RG-009)
     */
    public function create(string $sessionId, ?int $clientId = null): Panier
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO PANIER (session_id, client_id, date_creation, updated_at) 
             VALUES (:session_id, :client_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
        );

        $stmt->execute([
            'session_id' => $sessionId,
            'client_id' => $clientId,
        ]);

        $panierId = (int) $this->pdo->lastInsertId();

        return new Panier(
            id: $panierId,
            sessionId: $sessionId,
            clientId: $clientId,
            dateCreation: date('Y-m-d H:i:s'),
            updatedAt: date('Y-m-d H:i:s'),
        );
    }

    /**
     * Met à jour le client_id du panier (connexion après création)
     */
    public function updateClientId(int $panierId, int $clientId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE PANIER SET client_id = :client_id, updated_at = CURRENT_TIMESTAMP 
             WHERE id = :id'
        );

        return $stmt->execute([
            'client_id' => $clientId,
            'id' => $panierId,
        ]);
    }

    /**
     * Supprime un panier (RG-006 : après transformation en commande)
     */
    public function delete(int $panierId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM PANIER WHERE id = :id');
        return $stmt->execute(['id' => $panierId]);
    }

    /**
     * Récupère tous les paniers (utile pour statistiques/nettoyage)
     */
    public function findAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, session_id, client_id, date_creation, updated_at 
             FROM PANIER 
             ORDER BY date_creation DESC'
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($row) => $this->hydratePanier($row), $rows);
    }

    /**
     * Transforme une ligne BDD en objet Panier
     */
    private function hydratePanier(array $row): Panier
    {
        return new Panier(
            id: (int) $row['id'],
            sessionId: $row['session_id'],
            clientId: $row['client_id'] !== null ? (int) $row['client_id'] : null,
            dateCreation: $row['date_creation'],
            updatedAt: $row['updated_at'],
        );
    }
}
