<?php

declare(strict_types=1);

namespace WCDO\Entities;

class Admin
{
    public function __construct(
        private readonly int    $id,
        private readonly string $nom,
        private readonly string $email,
        private readonly string $motDePasse,
    ) {}

    public function getId(): int         { return $this->id; }
    public function getNom(): string     { return $this->nom; }
    public function getEmail(): string   { return $this->email; }

    public function verifierMotDePasse(string $motDePasseSaisi): bool
    {
        return password_verify($motDePasseSaisi, $this->motDePasse);
    }

    public function toArray(): array
    {
        return [
            'id'    => $this->id,
            'nom'   => $this->nom,
            'email' => $this->email,
        ];
    }
}
