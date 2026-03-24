<?php

declare(strict_types=1);

namespace WCDO\Entities;

class Panier
{
    public function __construct(
        private readonly int     $id,
        private readonly string  $sessionId,
        private readonly string  $dateCreation,
        private readonly string  $updatedAt,
        private readonly ?int    $clientId,
    ) {}

    public function getId(): int           { return $this->id; }
    public function getSessionId(): string { return $this->sessionId; }
    public function getClientId(): ?int    { return $this->clientId; }

    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'session_id'    => $this->sessionId,
            'client_id'     => $this->clientId,
            'date_creation' => $this->dateCreation,
            'updated_at'    => $this->updatedAt,
        ];
    }
}
