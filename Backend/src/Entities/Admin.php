<?php

declare(strict_types=1);

namespace WCDO\Entities;

class Admin
{
    public const ROLE_ADMINISTRATION = 'administration';
    public const ROLE_PREPARATION    = 'preparation';
    public const ROLE_ACCUEIL        = 'accueil';

    public function __construct(
        private readonly int    $id,
        private readonly string $nom,
        private readonly string $email,
        private readonly string $motDePasse,
        private readonly string $role,
    ) {
        if (!in_array($role, [self::ROLE_ADMINISTRATION, self::ROLE_PREPARATION, self::ROLE_ACCUEIL], true)) {
            throw new \InvalidArgumentException("Rôle invalide : '$role'. Valeurs acceptées : administration, preparation, accueil.");
        }
    }

    public function getId(): int         { return $this->id; }
    public function getNom(): string     { return $this->nom; }
    public function getEmail(): string   { return $this->email; }
    public function getRole(): string    { return $this->role; }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

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
            'role'  => $this->role,
        ];
    }
}
