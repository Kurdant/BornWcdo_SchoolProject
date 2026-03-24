<?php

declare(strict_types=1);

namespace WCDO\Entities;

class Categorie
{
    public function __construct(
        private readonly int    $id,
        private readonly string $nom,
    ) {}

    public function getId(): int     { return $this->id; }
    public function getNom(): string { return $this->nom; }

    public function toArray(): array
    {
        return [
            'id'  => $this->id,
            'nom' => $this->nom,
        ];
    }
}
