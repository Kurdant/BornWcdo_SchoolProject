<?php

declare(strict_types=1);

namespace WCDO\Entities;

class Produit
{
    public function __construct(
        private readonly int     $id,
        private readonly string  $nom,
        private readonly string  $description,
        private readonly float   $prix,
        private readonly int     $stock,
        private readonly int     $idCategorie,
        private readonly ?string $image,
        private readonly string  $dateCreation,
    ) {
        // RG : prix strictement positif
        if ($this->prix <= 0) {
            throw new \InvalidArgumentException('Le prix doit être supérieur à 0');
        }
        // RG : stock ne peut pas être négatif
        if ($this->stock < 0) {
            throw new \InvalidArgumentException('Le stock ne peut pas être négatif');
        }
    }

    public function getId(): int             { return $this->id; }
    public function getNom(): string          { return $this->nom; }
    public function getDescription(): string  { return $this->description; }
    public function getPrix(): float          { return $this->prix; }
    public function getStock(): int           { return $this->stock; }
    public function getIdCategorie(): int     { return $this->idCategorie; }
    public function getImage(): ?string       { return $this->image; }
    public function getDateCreation(): string { return $this->dateCreation; }

    // RG-001 : stock = 0 → produit indisponible
    public function estDisponible(): bool
    {
        return $this->stock > 0;
    }

    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'nom'           => $this->nom,
            'description'   => $this->description,
            'prix'          => $this->prix,
            'stock'         => $this->stock,
            'id_categorie'  => $this->idCategorie,
            'image'         => $this->image,
            'disponible'    => $this->estDisponible(),
            'date_creation' => $this->dateCreation,
        ];
    }
}
