<?php

declare(strict_types=1);

namespace WCDO\Entities;

class Client
{
    public function __construct(
        private readonly int    $id,
        private readonly string $prenom,
        private readonly string $nom,
        private readonly string $email,
        private readonly string $motDePasse,
        private readonly int    $pointsFidelite,
        private readonly string $dateCreation,
    ) {}

    public function getId(): int             { return $this->id; }
    public function getPrenom(): string       { return $this->prenom; }
    public function getNom(): string          { return $this->nom; }
    public function getEmail(): string        { return $this->email; }
    public function getPointsFidelite(): int  { return $this->pointsFidelite; }
    public function getDateCreation(): string { return $this->dateCreation; }

    // Vérifie le mot de passe saisi contre le hash bcrypt stocké en BDD
    public function verifierMotDePasse(string $motDePasseSaisi): bool
    {
        return password_verify($motDePasseSaisi, $this->motDePasse);
    }

    // Pas de getMotDePasse() — le hash ne doit jamais sortir de l'Entité
    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'prenom'          => $this->prenom,
            'nom'             => $this->nom,
            'email'           => $this->email,
            'points_fidelite' => $this->pointsFidelite,
            'date_creation'   => $this->dateCreation,
        ];
    }
}
